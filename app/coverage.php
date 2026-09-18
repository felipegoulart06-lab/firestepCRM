<?php
declare(strict_types=1);

function coverage_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = db();
    $real = is_pgsql() ? 'DOUBLE PRECISION' : 'REAL';
    if (is_pgsql()) {
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS address text');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS city text');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS state text');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS cep text');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS lat double precision');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS lng double precision');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS trade_name text');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS state_registration text');
        $pdo->exec('ALTER TABLE clients ADD COLUMN IF NOT EXISTS contact_name text');
        $pdo->exec("ALTER TABLE appointments ADD COLUMN IF NOT EXISTS visit_type text DEFAULT 'interno'");
        $pdo->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_agent_id text');
        $pdo->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_type text');
        $pdo->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_value numeric(12,2)');
        $pdo->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_amount numeric(12,2)');
        $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS document_kind text');
        $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS cpf text');
    } else {
        $clientCols = array_column($pdo->query('PRAGMA table_info(clients)')->fetchAll(), 'name');
        foreach (['address' => 'TEXT', 'city' => 'TEXT', 'state' => 'TEXT', 'cep' => 'TEXT', 'lat' => 'REAL', 'lng' => 'REAL', 'trade_name' => 'TEXT', 'state_registration' => 'TEXT', 'contact_name' => 'TEXT'] as $col => $def) {
            if (!in_array($col, $clientCols, true)) {
                $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
            }
        }
        $apptCols = array_column($pdo->query('PRAGMA table_info(appointments)')->fetchAll(), 'name');
        if (!in_array('visit_type', $apptCols, true)) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN visit_type TEXT DEFAULT 'interno'");
        }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
      id TEXT PRIMARY KEY,
      tenant_id TEXT NOT NULL,
      name TEXT NOT NULL,
      cnpj TEXT NOT NULL,
      address TEXT,
      city TEXT,
      state TEXT,
      cep TEXT,
      lat $real,
      lng $real,
      notes TEXT,
      created_at TEXT NOT NULL
    )");
    $extra = [
        'document_kind' => "TEXT DEFAULT 'cnpj'",
        'product_type' => 'TEXT',
        'phone' => 'TEXT',
        'email' => 'TEXT',
        'contact_name' => 'TEXT',
    ];
    if (is_pgsql()) {
        foreach ($extra as $col => $def) {
            $pdo->exec("ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS $col $def");
        }
    } else {
        $supCols = array_column($pdo->query('PRAGMA table_info(suppliers)')->fetchAll(), 'name');
        foreach ($extra as $col => $def) {
            if (!in_array($col, $supCols, true)) {
                $pdo->exec("ALTER TABLE suppliers ADD COLUMN $col $def");
            }
        }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_stops (
      id TEXT PRIMARY KEY,
      tenant_id TEXT NOT NULL,
      appointment_id TEXT NOT NULL,
      sort_order INTEGER DEFAULT 0,
      address TEXT NOT NULL,
      lat $real,
      lng $real,
      created_at TEXT NOT NULL
    )");
    $done = true;
}

function br_uf_centroids(): array
{
    return [
        'AC' => [-8.77, -70.55], 'AL' => [-9.57, -36.55], 'AP' => [1.41, -51.77], 'AM' => [-3.47, -65.10],
        'BA' => [-12.96, -41.55], 'CE' => [-5.20, -39.53], 'DF' => [-15.78, -47.93], 'ES' => [-19.19, -40.34],
        'GO' => [-15.98, -49.86], 'MA' => [-5.42, -45.44], 'MT' => [-12.64, -55.42], 'MS' => [-20.51, -54.54],
        'MG' => [-18.10, -44.38], 'PA' => [-3.79, -52.48], 'PB' => [-7.28, -36.72], 'PR' => [-24.89, -51.55],
        'PE' => [-8.38, -37.86], 'PI' => [-6.60, -42.28], 'RJ' => [-22.25, -42.66], 'RN' => [-5.81, -36.59],
        'RS' => [-30.17, -53.50], 'RO' => [-10.83, -63.34], 'RR' => [1.99, -61.33], 'SC' => [-27.45, -50.95],
        'SP' => [-22.19, -48.79], 'SE' => [-10.57, -37.45], 'TO' => [-9.46, -48.26],
    ];
}

function br_detect_uf(?string $text): string
{
    if (preg_match('/\b([A-Z]{2})\b/u', strtoupper((string)$text), $m) && isset(br_uf_centroids()[$m[1]])) {
        return $m[1];
    }
    return '';
}

function coverage_skip_remote_geo(): bool
{
    return env_str('FIRESTEP_SQLITE') !== null || env_str('FIRESTEP_NO_GEO') === '1';
}

function geocoder_token(): string
{
    return mapbox_public_token();
}

function geocoder_provider(): string
{
    $set = strtolower((string)(env_str('GEOCODER_PROVIDER') ?? ''));
    if (in_array($set, ['maptiler', 'mapbox', 'locationiq', 'nominatim'], true)) {
        return $set;
    }
    return geocoder_token() !== '' ? 'mapbox' : 'nominatim';
}

function geo_point_ok(float $lat, float $lng): bool
{
    return $lat >= -35 && $lat <= 6 && $lng >= -75 && $lng <= -32;
}

function geo_posted_point(mixed $latRaw, mixed $lngRaw): ?array
{
    if ($latRaw === null || $lngRaw === null || $latRaw === '' || $lngRaw === '') {
        return null;
    }
    $lat = (float)$latRaw;
    $lng = (float)$lngRaw;
    return geo_point_ok($lat, $lng) ? ['lat' => $lat, 'lng' => $lng] : null;
}

function geocode_search(string $query, int $limit = 6): array
{
    $query = trim($query);
    if ($query === '' || coverage_skip_remote_geo()) {
        return [];
    }
    $limit = max(1, min(8, $limit));
    $provider = geocoder_provider();
    $token = geocoder_token();
    $items = [];
    if ($provider === 'maptiler' && $token !== '') {
        $raw = coverage_http_json('https://api.maptiler.com/geocoding/'.rawurlencode($query).'.json?'.http_build_query([
            'key' => $token, 'language' => 'pt', 'country' => 'br', 'limit' => $limit,
        ]));
        foreach ((array)($raw['features'] ?? []) as $f) {
            $parsed = geocode_parse_feature($f);
            if ($parsed) {
                $items[] = $parsed;
            }
        }
    } elseif ($provider === 'mapbox' && $token !== '') {
        $raw = coverage_http_json('https://api.mapbox.com/geocoding/v5/mapbox.places/'.rawurlencode($query).'.json?'.http_build_query([
            'access_token' => $token, 'country' => 'BR', 'language' => 'pt', 'limit' => $limit, 'types' => 'address,place,poi',
        ]));
        foreach ((array)($raw['features'] ?? []) as $f) {
            $parsed = geocode_parse_feature($f);
            if ($parsed) {
                $items[] = $parsed;
            }
        }
    } elseif ($provider === 'locationiq' && $token !== '') {
        $raw = coverage_http_json('https://api.locationiq.com/v1/autocomplete?'.http_build_query([
            'key' => $token, 'q' => $query, 'countrycodes' => 'br', 'limit' => $limit, 'format' => 'json', 'normalizecity' => 1,
        ]));
        foreach (is_array($raw) ? $raw : [] as $f) {
            $parsed = geocode_parse_nominatim($f, (string)($f['display_name'] ?? ''));
            if ($parsed) {
                $items[] = $parsed;
            }
        }
    }
    if (!$items) {
        $raw = coverage_http_json('https://nominatim.openstreetmap.org/search?'.http_build_query([
            'format' => 'json', 'limit' => $limit, 'countrycodes' => 'br', 'q' => $query, 'addressdetails' => 1,
        ]));
        foreach (is_array($raw) ? $raw : [] as $f) {
            $parsed = geocode_parse_nominatim($f, (string)($f['display_name'] ?? ''));
            if ($parsed) {
                $items[] = $parsed;
            }
        }
    }
    return $items;
}

function geocode_parse_feature(array $f): ?array
{
    $coords = $f['center'] ?? $f['geometry']['coordinates'] ?? null;
    if (!is_array($coords) || count($coords) < 2) {
        return null;
    }
    $lng = (float)$coords[0];
    $lat = (float)$coords[1];
    if (!geo_point_ok($lat, $lng)) {
        return null;
    }
    $label = (string)($f['place_name'] ?? $f['place_name_pt'] ?? $f['properties']['label'] ?? $f['text'] ?? '');
    $ctx = $f['context'] ?? [];
    $city = '';
    $state = '';
    $cep = '';
    foreach (is_array($ctx) ? $ctx : [] as $c) {
        $id = (string)($c['id'] ?? '');
        if (str_starts_with($id, 'postcode') || str_starts_with($id, 'postal')) {
            $cep = (string)($c['text'] ?? '');
        }
        if (str_starts_with($id, 'place') || str_starts_with($id, 'locality')) {
            $city = (string)($c['text'] ?? '');
        }
        if (str_starts_with($id, 'region')) {
            $short = (string)($c['short_code'] ?? '');
            $state = str_contains($short, '-') ? strtoupper(substr($short, -2)) : br_detect_uf((string)($c['text'] ?? ''));
        }
    }
    $address = (string)($f['text'] ?? $f['properties']['name'] ?? '');
    $props = is_array($f['properties'] ?? null) ? $f['properties'] : [];
    $city = $city ?: (string)($props['city'] ?? $props['locality'] ?? '');
    $state = $state ?: br_detect_uf((string)($props['state'] ?? $label));
    $cep = $cep ?: (string)($props['postcode'] ?? '');
    return [
        'label' => $label !== '' ? $label : trim($address.' '.$city.' '.$state),
        'address' => $address,
        'city' => $city,
        'state' => $state,
        'cep' => $cep,
        'lat' => $lat,
        'lng' => $lng,
    ];
}

function geocode_parse_nominatim(array $f, string $label): ?array
{
    $lat = isset($f['lat']) ? (float)$f['lat'] : null;
    $lng = isset($f['lon']) ? (float)$f['lon'] : (isset($f['lng']) ? (float)$f['lng'] : null);
    if ($lat === null || $lng === null || !geo_point_ok($lat, $lng)) {
        return null;
    }
    $a = is_array($f['address'] ?? null) ? $f['address'] : [];
    $road = trim((string)($a['road'] ?? $a['pedestrian'] ?? ''));
    $num = trim((string)($a['house_number'] ?? ''));
    $suburb = trim((string)($a['suburb'] ?? $a['neighbourhood'] ?? ''));
    $address = trim($road.($num !== '' ? ', '.$num : '').($suburb !== '' ? ' — '.$suburb : ''));
    $city = (string)($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? '');
    $state = br_detect_uf((string)($a['state'] ?? '')) ?: br_detect_uf($label);
    $cep = (string)($a['postcode'] ?? '');
    return [
        'label' => $label !== '' ? $label : trim($address.' '.$city.' '.$state),
        'address' => $address !== '' ? $address : $label,
        'city' => $city,
        'state' => $state,
        'cep' => $cep,
        'lat' => $lat,
        'lng' => $lng,
    ];
}

function locate_br_address(string $address, ?string $city = null, ?string $state = null, ?string $cep = null): array
{
    $parts = array_filter([$address, $city, $state ? strtoupper($state) : null, $cep ? 'CEP '.$cep : null, 'Brasil']);
    $query = trim(implode(', ', $parts));
    $digits = br_digits($cep);
    if (!coverage_skip_remote_geo() && strlen($digits) === 8) {
        $via = coverage_http_json('https://viacep.com.br/ws/'.$digits.'/json/');
        if (is_array($via) && empty($via['erro'])) {
            $city = $city ?: (string)($via['localidade'] ?? '');
            $state = $state ?: (string)($via['uf'] ?? '');
            if ($address === '' || $address === $digits) {
                $address = trim(($via['logradouro'] ?? '').' '.($via['bairro'] ?? ''));
            }
            $query = trim(implode(', ', array_filter([$address, $city, $state, 'Brasil'])));
        }
    }
    if (!coverage_skip_remote_geo()) {
        $hits = geocode_search($query, 1);
        if ($hits && isset($hits[0]['lat'], $hits[0]['lng'])) {
            return ['lat' => (float)$hits[0]['lat'], 'lng' => (float)$hits[0]['lng']];
        }
    }
    $uf = strtoupper(trim((string)$state)) ?: br_detect_uf($query);
    $c = br_uf_centroids()[$uf] ?? [-14.24, -51.93];
    return ['lat' => $c[0], 'lng' => $c[1]];
}

function coverage_http_json(string $url): mixed
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: FirestepCRM/1.0 abrangencia\r\nAccept: application/json\r\n",
            'timeout' => 6,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    return json_decode($raw, true);
}

function br_map_xy(float $lat, float $lng): array
{
    $x = ($lng + 74.2) / 40.2 * 1000;
    $y = (5.8 - $lat) / 40.2 * 1000;
    return [round(max(12, min(988, $x)), 1), round(max(12, min(988, $y)), 1)];
}

function save_appointment_visits(string $tenantId, string $appointmentId, array $in): void
{
    coverage_ensure_schema();
    $external = !empty($in['external_visit']) && $in['external_visit'] !== '0';
    q("UPDATE appointments SET visit_type=? WHERE id=? AND tenant_id=?", [$external ? 'externo' : 'interno', $appointmentId, $tenantId]);
    q('DELETE FROM appointment_stops WHERE appointment_id=? AND tenant_id=?', [$appointmentId, $tenantId]);
    if (!$external) {
        return;
    }
    $lines = $in['visit_addresses'] ?? [];
    if (!is_array($lines)) {
        $lines = [$lines];
    }
    $lats = $in['visit_lats'] ?? [];
    $lngs = $in['visit_lngs'] ?? [];
    $i = 0;
    foreach ($lines as $idx => $line) {
        $addr = trim((string)$line);
        if ($addr === '') {
            continue;
        }
        $pos = geo_posted_point($lats[$idx] ?? null, $lngs[$idx] ?? null) ?: locate_br_address($addr);
        q('INSERT INTO appointment_stops(id,tenant_id,appointment_id,sort_order,address,lat,lng,created_at) VALUES(?,?,?,?,?,?,?,?)', [
            uid(), $tenantId, $appointmentId, $i, $addr, $pos['lat'], $pos['lng'], now(),
        ]);
        $i++;
        if (!coverage_skip_remote_geo()) {
            usleep(150000);
        }
    }
}

function locate_client_if_cnpj(string $tenantId, string $clientId, ?string $document, ?string $address, ?string $city, ?string $state, ?string $cep, mixed $latRaw = null, mixed $lngRaw = null): void
{
    coverage_ensure_schema();
    $lat = null;
    $lng = null;
    if (br_doc_kind_from_value($document) === 'cnpj' && trim((string)$address.$city.$state.$cep) !== '') {
        $pos = geo_posted_point($latRaw, $lngRaw) ?: locate_br_address((string)$address, $city, $state, $cep);
        $lat = $pos['lat'];
        $lng = $pos['lng'];
    }
    q('UPDATE clients SET address=?, city=?, state=?, cep=?, lat=?, lng=? WHERE id=? AND tenant_id=?', [
        $address ?: null, $city ?: null, $state ? strtoupper($state) : null, $cep ?: null, $lat, $lng, $clientId, $tenantId,
    ]);
}

function coverage_pins(string $tenantId): array
{
    coverage_ensure_schema();
    $pins = [];
    foreach (all('SELECT id,name,cnpj,document_kind,address,city,state,lat,lng,phone FROM suppliers WHERE tenant_id=?', [$tenantId]) as $row) {
        $kind = strtolower((string)($row['document_kind'] ?? '')) ?: br_doc_kind_from_value($row['cnpj'] ?? '');
        if ($kind !== 'cnpj' || $row['lat'] === null || $row['lng'] === null) {
            continue;
        }
        if ((float)$row['lat'] === 0.0 && (float)$row['lng'] === 0.0) {
            continue;
        }
        $addr = trim($row['address'].' '.$row['city'].' '.$row['state']);
        $pins[] = [
            'kind' => 'supplier',
            'name' => (string)$row['name'],
            'label' => $row['name'],
            'phone' => phone_fmt($row['phone'] ?? null),
            'info' => trim(format_br_document($row['cnpj'], 'cnpj').($addr !== '' ? ' · '.$addr : '')),
            'detail' => format_br_document($row['cnpj'], 'cnpj').' · '.$addr,
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lng'],
        ];
    }
    foreach (all('SELECT id,name,cpf,address,city,state,lat,lng,phone,whatsapp FROM clients WHERE tenant_id=?', [$tenantId]) as $row) {
        if (br_doc_kind_from_value($row['cpf'] ?? '') !== 'cnpj' || $row['lat'] === null || $row['lng'] === null) {
            continue;
        }
        if ((float)$row['lat'] === 0.0 && (float)$row['lng'] === 0.0) {
            continue;
        }
        $addr = trim($row['address'].' '.$row['city'].' '.$row['state']);
        $pins[] = [
            'kind' => 'client',
            'name' => (string)$row['name'],
            'label' => $row['name'],
            'phone' => phone_fmt($row['phone'] ?: $row['whatsapp']),
            'info' => trim(format_br_document($row['cpf'], 'cnpj').($addr !== '' ? ' · '.$addr : '')),
            'detail' => format_br_document($row['cpf'], 'cnpj').' · '.$addr,
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lng'],
        ];
    }
    foreach (all("SELECT s.id,s.address,s.lat,s.lng,c.name client_name,c.phone client_phone,c.whatsapp client_whatsapp,a.starts_at
        FROM appointment_stops s
        JOIN appointments a ON a.id=s.appointment_id AND a.tenant_id=s.tenant_id
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        WHERE s.tenant_id=? AND a.visit_type='externo' AND a.source='Manual' AND a.status!='CANCELLED'", [$tenantId]) as $row) {
        if ($row['lat'] === null || $row['lng'] === null) {
            continue;
        }
        if ((float)$row['lat'] === 0.0 && (float)$row['lng'] === 0.0) {
            continue;
        }
        $when = date('d/m/Y H:i', strtotime($row['starts_at']));
        $pins[] = [
            'kind' => 'visit',
            'name' => 'Agendamento · '.$row['client_name'],
            'label' => 'Agendamento · '.$row['client_name'],
            'phone' => phone_fmt($row['client_phone'] ?: $row['client_whatsapp']),
            'info' => $when.' · '.$row['address'],
            'detail' => $when.' · '.$row['address'],
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lng'],
        ];
    }
    return $pins;
}

function brazil_svg_path(): string
{
    $pts = [
        [-51.6, 4.3], [-50.2, 1.8], [-48.4, -0.1], [-44.0, -2.4], [-41.8, -2.9], [-37.1, -4.8],
        [-34.8, -7.1], [-34.9, -8.6], [-35.6, -9.6], [-37.1, -11.0], [-38.9, -13.0], [-38.9, -15.5],
        [-39.3, -17.2], [-39.6, -18.2], [-40.6, -20.2], [-40.4, -22.0], [-41.9, -22.6], [-43.2, -23.0],
        [-44.7, -23.4], [-47.9, -25.3], [-48.6, -26.0], [-48.7, -28.2], [-49.5, -29.4], [-52.1, -32.1],
        [-53.5, -33.7], [-53.4, -32.4], [-56.8, -30.2], [-57.7, -30.2], [-58.2, -27.9], [-56.1, -24.1],
        [-54.1, -22.6], [-57.6, -22.1], [-58.0, -20.0], [-58.2, -16.2], [-60.1, -13.4], [-62.2, -13.0],
        [-65.0, -11.9], [-66.6, -9.9], [-73.1, -10.0], [-73.0, -7.4], [-70.0, -7.0], [-69.4, -4.0],
        [-67.0, -4.0], [-65.9, -2.4], [-64.0, -2.0], [-60.0, -2.0], [-59.9, 1.6], [-51.9, 2.1],
    ];
    $d = '';
    foreach ($pts as $i => $p) {
        $xy = br_map_xy($p[1], $p[0]);
        $d .= ($i === 0 ? 'M' : 'L').$xy[0].' '.$xy[1].' ';
    }
    return trim($d).' Z';
}

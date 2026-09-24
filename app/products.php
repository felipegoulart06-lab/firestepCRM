<?php
declare(strict_types=1);

function products_catalog_defaults(): array
{
    $now = now();
    return [
        [
            'id' => 'prod-sistema-site',
            'title' => 'Sistema + site de agendamento',
            'summary' => 'CRM e site para o cliente marcar horário sozinho, sem perder pedido no WhatsApp.',
            'description' => 'Pacote para quem precisa de presença digital e agenda no mesmo lugar: site de agendamento ligado ao FirestepCRM.',
            'sort_order' => 10,
        ],
        [
            'id' => 'prod-landing-campanha',
            'title' => 'Landing page + campanha de marketing',
            'summary' => 'Página de captura e campanha para gerar conversas qualificadas.',
            'description' => 'Landing com oferta clara e campanha para trazer leads até o WhatsApp e o CRM.',
            'sort_order' => 20,
        ],
        [
            'id' => 'prod-chatbot-whatsapp',
            'title' => 'Chatbot para atendimento automático no WhatsApp + sistema',
            'summary' => 'Respostas automáticas no WhatsApp ligadas à operação no painel.',
            'description' => 'Atendimento inicial no WhatsApp com passagem organizada para a equipe no sistema.',
            'sort_order' => 30,
        ],
        [
            'id' => 'prod-automatize',
            'title' => 'Automatize tarefas do dia a dia',
            'summary' => 'Lembretes, mensagens e rotinas que a equipe não precisa repetir à mão.',
            'description' => 'Automação de tarefas repetidas: confirmação, lembrete e follow-up com a operação no CRM.',
            'sort_order' => 40,
        ],
        [
            'id' => 'prod-imagens',
            'title' => 'Imagens personalizadas para a sua empresa',
            'summary' => 'Artes e visuais no padrão da sua marca para divulgação e atendimento.',
            'description' => 'Pacote de imagens feitas para a identidade da empresa, pronta para redes e materiais.',
            'sort_order' => 50,
        ],
    ];
}

function products_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec("CREATE TABLE IF NOT EXISTS platform_products (
              id text PRIMARY KEY,
              title text NOT NULL,
              summary text NOT NULL DEFAULT '',
              description text NOT NULL DEFAULT '',
              image_url text NOT NULL DEFAULT '',
              link_url text NOT NULL DEFAULT '',
              sort_order integer NOT NULL DEFAULT 0,
              active boolean NOT NULL DEFAULT true,
              created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
              updated_at timestamptz NOT NULL DEFAULT timezone('utc', now())
            )");
            db()->exec("CREATE TABLE IF NOT EXISTS platform_product_leads (
              id text PRIMARY KEY,
              product_id text NOT NULL,
              tenant_id text NOT NULL,
              user_id text NOT NULL,
              created_at timestamptz NOT NULL DEFAULT timezone('utc', now())
            )");
            db()->exec('CREATE INDEX IF NOT EXISTS platform_products_sort ON platform_products(sort_order, title)');
            db()->exec('CREATE INDEX IF NOT EXISTS platform_product_leads_created ON platform_product_leads(created_at DESC)');
        } else {
            db()->exec("CREATE TABLE IF NOT EXISTS platform_products (
              id TEXT PRIMARY KEY,
              title TEXT NOT NULL,
              summary TEXT NOT NULL DEFAULT '',
              description TEXT NOT NULL DEFAULT '',
              image_url TEXT NOT NULL DEFAULT '',
              link_url TEXT NOT NULL DEFAULT '',
              sort_order INTEGER NOT NULL DEFAULT 0,
              active INTEGER NOT NULL DEFAULT 1,
              created_at TEXT NOT NULL,
              updated_at TEXT NOT NULL
            )");
            db()->exec("CREATE TABLE IF NOT EXISTS platform_product_leads (
              id TEXT PRIMARY KEY,
              product_id TEXT NOT NULL,
              tenant_id TEXT NOT NULL,
              user_id TEXT NOT NULL,
              created_at TEXT NOT NULL
            )");
            db()->exec('CREATE INDEX IF NOT EXISTS platform_products_sort ON platform_products(sort_order, title)');
            db()->exec('CREATE INDEX IF NOT EXISTS platform_product_leads_created ON platform_product_leads(created_at)');
        }
        products_seed_once();
    } catch (Throwable $e) {
        $done = false;
    }
}

function products_seed_once(): void
{
    try {
        $flag = one("SELECT key FROM platform_settings WHERE key=?", ['products_seeded']);
    } catch (Throwable $e) {
        return;
    }
    if ($flag) {
        return;
    }
    $now = now();
    foreach (products_catalog_defaults() as $row) {
        if (one('SELECT id FROM platform_products WHERE id=?', [$row['id']])) {
            continue;
        }
        q(
            'INSERT INTO platform_products(id,title,summary,description,image_url,link_url,sort_order,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'.sql_lit_bool(true).',?,?)',
            [$row['id'], $row['title'], $row['summary'], $row['description'], '', '', (int)$row['sort_order'], $now, $now]
        );
    }
    try {
        q('INSERT INTO platform_settings(key,value,updated_at) VALUES(?,?,?)', ['products_seeded', '1', $now]);
    } catch (Throwable $e) {
        // já marcado em outra requisição
    }
}

function products_is_on(array $row): bool
{
    $v = $row['active'] ?? 0;
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

function products_all(): array
{
    products_ensure_schema();
    return all('SELECT * FROM platform_products ORDER BY sort_order ASC, title ASC');
}

function products_public(): array
{
    products_ensure_schema();
    return array_values(array_filter(products_all(), static fn(array $row): bool => products_is_on($row)));
}

function products_one(string $id): ?array
{
    products_ensure_schema();
    if ($id === '') {
        return null;
    }
    return one('SELECT * FROM platform_products WHERE id=?', [$id]) ?: null;
}

function products_normalize_url(string $raw): string
{
    $url = trim($raw);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https://#i', $url)) {
        throw new RuntimeException('O link deve começar com https://');
    }
    return $url;
}

function products_image_from_post(string $kept): string
{
    if (post('remove_image') === '1') {
        $kept = '';
    }
    if (!preg_match('#^https://#i', $kept)) {
        $kept = '';
    }
    $file = $_FILES['image'] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $kept;
    }
    if ((int)($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível ler a imagem.');
    }
    if ((int)($file['size'] ?? 0) > 1000000) {
        throw new RuntimeException('A imagem deve ter no máximo 1 MB.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $bin = $tmp !== '' ? (string)file_get_contents($tmp) : '';
    $info = $bin !== '' ? @getimagesizefromstring($bin) : false;
    if (!is_array($info) || empty($info['mime'])) {
        throw new RuntimeException('Envie JPEG, PNG ou WEBP.');
    }
    $mime = strtolower((string)$info['mime']);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '') {
        throw new RuntimeException('Envie JPEG, PNG ou WEBP.');
    }
    if (!function_exists('r2_ready') || !r2_ready()) {
        throw new RuntimeException('A Cloudflare R2 não está configurada para guardar a imagem.');
    }
    $key = 'produtos/'.bin2hex(random_bytes(8)).'.'.$ext;
    $put = r2_put_bytes($key, $bin, $mime);
    if (empty($put['ok']) || ($put['url'] ?? '') === '') {
        throw new RuntimeException('Não foi possível guardar a imagem na Cloudflare.');
    }
    return (string)$put['url'];
}

function products_save_from_post(): array
{
    products_ensure_schema();
    $id = trim((string)post('id', ''));
    $title = trim((string)post('title', ''));
    $summary = trim((string)post('summary', ''));
    $description = trim((string)post('description', ''));
    if ($title === '') {
        throw new RuntimeException('Informe o nome do produto.');
    }
    if (strlen($title) > 120) {
        throw new RuntimeException('O nome deve ter no máximo 120 caracteres.');
    }
    $current = $id !== '' ? products_one($id) : null;
    if ($id !== '' && !$current) {
        throw new RuntimeException('Produto não encontrado.');
    }
    $image = products_image_from_post((string)($current['image_url'] ?? post('image_url', '')));
    $link = products_normalize_url((string)post('link_url', ''));
    $sort = (int)post('sort_order', '0');
    $active = post('active') === '1';
    $now = now();
    if ($current) {
        q(
            'UPDATE platform_products SET title=?, summary=?, description=?, image_url=?, link_url=?, sort_order=?, active='.sql_lit_bool($active).', updated_at=? WHERE id=?',
            [$title, $summary, $description, $image, $link, $sort, $now, $id]
        );
        return ['ok' => true, 'id' => $id, 'created' => false];
    }
    $id = uid();
    q(
        'INSERT INTO platform_products(id,title,summary,description,image_url,link_url,sort_order,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'.sql_lit_bool($active).',?,?)',
        [$id, $title, $summary, $description, $image, $link, $sort, $now, $now]
    );
    return ['ok' => true, 'id' => $id, 'created' => true];
}

function products_set_active(string $id, bool $on): bool
{
    products_ensure_schema();
    if (!products_one($id)) {
        return false;
    }
    q('UPDATE platform_products SET active='.sql_lit_bool($on).', updated_at=? WHERE id=?', [now(), $id]);
    return true;
}

function products_delete(string $id): bool
{
    products_ensure_schema();
    if (!products_one($id)) {
        return false;
    }
    q('DELETE FROM platform_product_leads WHERE product_id=?', [$id]);
    q('DELETE FROM platform_products WHERE id=?', [$id]);
    return true;
}

function products_interest(array $tenant, array $user, string $productId): array
{
    products_ensure_schema();
    $product = products_one($productId);
    if (!$product || !products_is_on($product)) {
        return ['ok' => false, 'message' => 'Produto indisponível.'];
    }
    $tid = (string)($tenant['id'] ?? '');
    $uid = (string)($user['id'] ?? '');
    if ($tid === '' || $uid === '') {
        return ['ok' => false, 'message' => 'Sessão inválida.'];
    }
    if (!rate_ok('prod-lead:'.$tid.':'.$productId, 3, 86400)) {
        return ['ok' => false, 'message' => 'Já registramos o seu interesse neste produto. Em breve a equipe entra em contato.'];
    }
    q(
        'INSERT INTO platform_product_leads(id,product_id,tenant_id,user_id,created_at) VALUES(?,?,?,?,?)',
        [uid(), $productId, $tid, $uid, now()]
    );
    if (function_exists('assistant_ensure_schema')) {
        assistant_ensure_schema();
    }
    if (function_exists('assistant_submit')) {
        assistant_submit($tid, $uid, 'Tenho interesse no produto: '.$product['title']);
    }
    audit($tid, $uid, 'product.interest', 'platform_product', $productId);
    return ['ok' => true, 'message' => 'Interesse registrado. A equipe da plataforma entra em contato.'];
}

function products_leads(int $limit = 40): array
{
    products_ensure_schema();
    $limit = max(1, min(80, $limit));
    return all(
        "SELECT l.id, l.created_at, l.product_id, p.title product_title,
                t.business_name, t.name tenant_name, u.name user_name
         FROM platform_product_leads l
         LEFT JOIN platform_products p ON p.id=l.product_id
         LEFT JOIN tenants t ON t.id=l.tenant_id
         LEFT JOIN users u ON u.id=l.user_id
         ORDER BY l.created_at DESC
         LIMIT ".$limit
    );
}

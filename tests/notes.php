<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-notes-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';

db();
notes_ensure_schema();

$fail = 0;
function expect($ok, string $msg): void
{
    global $fail;
    if ($ok) {
        echo "OK  $msg\n";
        return;
    }
    $fail++;
    echo "FAIL $msg\n";
}

$now = now();
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-n', 'N', 'Empresa N', 'emp-n-notes', 'outros', 'n@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'crm-n', 'ten-n', 'Dono', 'crm-n@ex.com', 'crm-n', 'x', 'user_crm', 0, 1, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ag-n', 'ten-n', 'Plantao', 'ag-n@ex.com', 'ag-n', 'x', 'user_agent', 0, 1, $now,
]);

q('INSERT INTO user_notes(id,tenant_id,user_id,title,body,note_date,show_on_agenda,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'note-cal', 'ten-n', 'ag-n', 'Troca de turno', 'Chave extra no gaveteiro.', date('Y-m-d'), db_bool(true), $now, $now,
]);
q('INSERT INTO user_notes(id,tenant_id,user_id,title,body,note_date,show_on_agenda,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'note-hide', 'ten-n', 'ag-n', 'Recado interno', 'Só o plantão lê.', date('Y-m-d'), db_bool(false), $now, $now,
]);

$from = date('Y-m-d', strtotime('-2 days')).' 00:00:00';
$to = date('Y-m-d', strtotime('+2 days')).' 23:59:59';
$ev = notes_agenda_events('ten-n', $from, $to);
$ids = array_column($ev, 'id');
expect($ids === ['note-cal'], 'só anotação com SALVAR NA AGENDA entra no calendário');
expect(($ev[0]['kind'] ?? '') === 'note', 'evento da agenda tem kind note');

$crm = ['id' => 'crm-n', 'role' => 'user_crm'];
$agent = ['id' => 'ag-n', 'role' => 'user_agent'];
$other = ['id' => 'outro', 'role' => 'user_agent'];
$note = one('SELECT * FROM user_notes WHERE id=?', ['note-cal']);
expect(note_can_manage($crm, $note), 'user_crm pode gerenciar qualquer anotação');
expect(note_can_manage($agent, $note), 'autor pode gerenciar a própria anotação');
expect(!note_can_manage($other, $note), 'outro agente não edita anotação alheia');

$root = dirname(__DIR__);
$view = file_get_contents($root.'/views/app/anotacoes.php');
$nav = file_get_contents($root.'/views/layout_app_start.php');
$agenda = file_get_contents($root.'/views/app/agenda.php');
$index = file_get_contents($root.'/public/index.php');
expect(str_contains($view, 'notes-grid') && str_contains($view, 'note-card'), 'anotações listam em cards');
expect(str_contains($view, 'SALVAR NA AGENDA') && str_contains($view, 'name="show_on_agenda"'), 'formulário tem SALVAR NA AGENDA');
expect(str_contains($nav, '/app/anotacoes') && str_contains($nav, 'Anotações'), 'menu Anotações no sidebar');
expect(str_contains($agenda, "kind === 'note'") && str_contains($agenda, '/app/anotacoes?ver='), 'Agenda pinta e abre anotações');
expect(str_contains($index, "path === '/app/anotacoes'") && str_contains($index, 'notes_agenda_events'), 'rotas e merge na Agenda');

@unlink($tmp);
if ($fail) {
    fwrite(STDERR, "$fail teste(s) de anotações falharam.\n");
    exit(1);
}
echo "Anotações (logbook e agenda) aprovadas.\n";

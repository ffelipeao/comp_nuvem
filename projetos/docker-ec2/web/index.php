<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$produtos = [];
$erro = null;
try {
    $host = getenv('DB_HOST') ?: 'banco';
    $pdo = new PDO(
        "pgsql:host={$host};port=5432;dbname=loja;connect_timeout=3",
        'app_leitura',
        getenv('DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $produtos = $pdo->query('SELECT id, nome, preco, estoque FROM public.produtos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(503);
    $erro = 'Não foi possível consultar o banco de dados. Consulte os logs do container web.';
    error_log('Falha PostgreSQL: ' . $e->getMessage());
}
function h(mixed $valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Loja — Docker na EC2</title>
<style>
body{font:18px system-ui;max-width:900px;margin:40px auto;padding:0 20px;background:#f4f6fa;color:#172339}
table{border-collapse:collapse;width:100%;background:white}th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd}
.erro{padding:20px;background:#ffe1e1}code{background:#e4e8ef;padding:3px}
</style>
<h1>Produtos cadastrados</h1>
<p>Apache + PHP consultando PostgreSQL pela rede Docker.</p>
<p>Container web: <code><?= h(gethostname()) ?></code></p>
<?php if ($erro !== null): ?>
<p class="erro" role="alert"><?= h($erro) ?></p>
<?php else: ?>
<p>Consulta concluída. Total: <?= count($produtos) ?> produto(s).</p>
<table>
<thead><tr><th>ID</th><th>Produto</th><th>Preço</th><th>Estoque</th></tr></thead>
<tbody>
<?php foreach ($produtos as $produto): ?>
<tr><td><?= h($produto['id']) ?></td><td><?= h($produto['nome']) ?></td>
<td>R$ <?= h(number_format((float) $produto['preco'], 2, ',', '.')) ?></td>
<td><?= h($produto['estoque']) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<p><a href="/">Consultar novamente</a></p>
</html>

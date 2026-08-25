<?php
// conexao.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Função nativa para ler o arquivo .env manualmente
$caminhoEnv = __DIR__ . '/.env';

if (!file_exists($caminhoEnv)) {
    die("Erro: Arquivo .env não encontrado na raiz do projeto.");
}

// Lê o arquivo linha por linha
$linhas = file($caminhoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($linhas as $linha) {
    // Ignora as linhas de comentário (que começam com #)
    if (strpos(trim($linha), '#') === 0) continue;
    
    // Separa o Nome da Variável do Valor (antes e depois do sinal de =)
    if (strpos($linha, '=') !== false) {
        list($nome, $valor) = explode('=', $linha, 2);
        // Salva na variável global $_ENV do PHP
        $_ENV[trim($nome)] = trim($valor);
    }
}

// 2. Pega as variáveis que acabamos de ler
$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$charset = 'utf8mb4';

// 3. Monta a conexão
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "Conexão nativa realizada com sucesso!";
} catch (\PDOException $e) {
    die("Erro de conexão com o Banco de Dados: " . $e->getMessage());
}
?>
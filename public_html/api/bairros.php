<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// public_html/api/bairros.php

header('Content-Type: application/json; charset=utf-8');

// Ajuste o caminho do require_once caso seu arquivo de conexão esteja em outra pasta
require_once __DIR__ . '/../../conexao.php';

try {
    // Verifica se a tabela bairros existe
    $stmt = $pdo->query("SELECT id, nome, taxa FROM bairros ORDER BY nome ASC");
    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bairrosFormatados = [];
    foreach ($bairros as $b) {
        $bairrosFormatados[] = [
            'id' => (int) $b['id'],
            'nome' => $b['nome'],
            'taxa' => (float) $b['taxa']
        ];
    }

    echo json_encode($bairrosFormatados, JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao buscar bairros no banco: ' . $e->getMessage()
    ]);
}
?>
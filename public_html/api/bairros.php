<?php
// public_html/api/bairros.php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

try {
    $stmt = $pdo->query("SELECT id, nome, taxa FROM bairros ORDER BY nome ASC");
    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajusta o preço da taxa para float caso queira forçar testes para 0.01 (ou remova se preferir o valor real do banco)
    $bairrosFormatados = [];
    foreach ($bairros as $b) {
        $bairrosFormatados[] = [
            'id' => $b['id'],
            'nome' => $b['nome'],
            'taxa' => (float) $b['taxa']
        ];
    }

    echo json_encode($bairrosFormatados, JSON_UNESCAPED_UNICODE);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>
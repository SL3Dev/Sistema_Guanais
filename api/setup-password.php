<?php
/**
 * setup-password.php
 * Script para redefinir a senha do administrador
 * Execute uma vez no navegador: http://localhost/PROJETO/api/setup-password.php
 */

// Configurações do banco
$host = 'localhost';
$dbname = 'espaco_guanais';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Gerar hash da senha '0301'
    $senhaHash = password_hash('0301', PASSWORD_BCRYPT);
    
    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        // Atualizar senha
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE usuario = 'admin'");
        $stmt->execute([$senhaHash]);
        echo "✅ Senha do administrador atualizada com sucesso!<br>";
        echo "Senha: <strong>0301</strong><br>";
        echo "Hash: " . $senhaHash . "<br><br>";
        echo "<a href='../index.html'>Ir para o login</a>";
    } else {
        // Criar usuário admin
        $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, senha, nome, email) VALUES ('admin', ?, 'Administrador', 'admin@espacoguanais.com.br')");
        $stmt->execute([$senhaHash]);
        echo "✅ Usuário administrador criado com sucesso!<br>";
        echo "Senha: <strong>0301</strong><br>";
        
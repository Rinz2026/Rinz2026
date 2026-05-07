<?php
session_start();
 
function estaLogueado() {
    return isset($_SESSION['admin_id']);
}
 
function requiereLogin() {
    if (!estaLogueado()) {
        header('Location: login.php');
        exit;
    }
}
 
function login($username, $password, $conn) {
    $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
 
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            return true;
        }
    }
    return false;
}
 
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
 
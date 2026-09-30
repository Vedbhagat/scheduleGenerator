<?php

require_once 'dbConnect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
error_reporting(0);
$action = $_REQUEST['action'] ?? 'login';

// REGISTRATION
if ($action == 'register') {
    $username = strtoupper($_POST['username'] ?? '');
    $password = strtoupper($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'statusCode' => 400, 'statusDescription' => 'All fields are required for registration.']);
        exit;
    }

    // Check if user exists
    $result = $conn->execute_query("SELECT USERNAME FROM USER WHERE USERNAME = ?", [$username]);
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'statusCode' => 409, 'statusDescription' => 'Username already taken.']);
        exit;
    }

    // Hash password and insert
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    if ($conn->execute_query("INSERT INTO USER (USERNAME, PASSWORD) VALUES (?, ?)", [$username, $hashed_password])) {
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $_SESSION['username'] = $username;
        http_response_code(201);
        echo json_encode(['status' => 'success', 'statusCode' => 201, 'responseBody' => 'login.html']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'statusCode' => 500, 'statusDescription' => 'Registration failed.']);
    }
    exit;
}

// LOGIN 
if ($action == 'login') {
    $username = strtoupper($_POST['username'] ?? '');
    $password = strtoupper($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'statusCode' => 400, 'statusDescription' => 'Username and password are required.']);
        exit;
    }

    $result = $conn->execute_query("SELECT USERNAME, PASSWORD FROM USER WHERE USERNAME = ?", [$username]);
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['PASSWORD'])) {
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $_SESSION['username'] = $username;
        echo json_encode(['status' => 'success', 'responseBody' => 'index.html', 'username' => $_SESSION['username']]);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'statusCode' => 401, 'statusDescription' => 'Invalid credentials.']);
    }
    exit;
}

// LOGOUT
if ($action == 'logout') {
    session_unset();
    session_destroy();
    echo json_encode([
        'status' => 'success', 
        'statusCode' => 200, 
        'responseBody' => './login.html' 
    ]);
    exit;
}

// STATUS CHECK
if ($action == 'status') {
    echo json_encode([
        'status' => 'success',
        'loggedIn' => isset($_SESSION['username']),
        'username' => $_SESSION['username']
    ]);
    exit;
}
<?php
session_start(); 
include('conn.php');

if (isset($_POST['pass'])) {
    
    $email = "admin";
    $pass = $_POST['pass'];
    
    $sql = "SELECT * FROM users WHERE email='$email' AND password='$pass'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $_SESSION['user'] = $email; 
        header("Location: ../Frontend/ADMIN/index2.php");
        
    } else {
        echo "Invalid password";
    }
}

if (isset($_POST['pass'])) {
    
    $email = "admin";
    $pass = $_POST['pass'];
    
    $sql = "SELECT * FROM users WHERE email='$email' AND password='$pass'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $_SESSION['user'] = $email; 
        header("Location: ../Frontend/ADMIN/index2.php");
        
    } else {
        echo "Invalid password";
    }
}
?>
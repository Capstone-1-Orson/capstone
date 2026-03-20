<?php
include('conn.php');

if (isset($_POST['save_user'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $position = $_POST['position'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (name, email, password, position) 
            VALUES ('$name', '$email', '$hashed_password', '$position')";

    if (mysqli_query($conn, $sql)) {
        header("Location: ../Frontend/staff-list.php?success=1");
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>
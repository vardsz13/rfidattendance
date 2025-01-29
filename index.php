<?php
$hashed_password = '$2b$10$Jp2WhmFTTmcbdwH4eK3GJudwAuFTTRyRv0RAuKBL8i5aiO0BRXRUC'; // Stored hash
$entered_password = 'password'; // User input

if (password_verify($entered_password, $hashed_password)) {
    echo "Login successful!";
} else {
    echo "Invalid password!";
}
?>

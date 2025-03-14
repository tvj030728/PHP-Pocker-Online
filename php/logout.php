<?php
require_once 'config.php';

// Delete session information
session_unset();
session_destroy();

// Success response
echo json_encode(['success' => true]);
?> 
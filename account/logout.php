<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
require_post(); verify_csrf();
log_out_player();
flash('success', 'You are logged out.');
redirect('account/');

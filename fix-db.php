<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';
require_once 'includes/class-reg-activator.php';
Olama_Reg_Activator::create_agreements_tables();
echo "Tables created.";

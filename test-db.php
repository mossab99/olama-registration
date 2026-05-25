<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;

echo "Clauses table exists? ";
$table_clauses = $wpdb->prefix . 'olama_agreement_clauses';
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table_clauses'");
var_dump($exists);

echo "\nParticipant IDs for AGR 2:\n";
$table_agr = $wpdb->prefix . 'olama_agreements';
$agr = $wpdb->get_row("SELECT id, participant_id, participant_ids FROM $table_agr WHERE id = 2");
print_r($agr);

<?php
require_once __DIR__ . '/Front/conexion.php';

echo "1. Validating tbl_estado_miembro:\n";
$stmt = $pdo->query("SELECT * FROM tbl_estado_miembro WHERE id_estado_miembro = 0");
$estado_cero = $stmt->fetch();
if ($estado_cero) {
    echo "  - ID 0 EXISTS: " . print_r($estado_cero, true) . "\n";
} else {
    echo "  - ID 0 does NOT exist. Safe to insert.\n";
}

echo "\n2. Validating tbl_miembro 'estado' column:\n";
$stmt = $pdo->query("
    SELECT column_name, data_type, character_maximum_length 
    FROM information_schema.columns 
    WHERE table_name = 'tbl_miembro' AND column_name = 'estado'
");
$column_info = $stmt->fetch();
echo "  - Column Info: " . print_r($column_info, true) . "\n";

echo "\n3. Checking constraints on tbl_miembro 'estado' column:\n";
$stmt = $pdo->query("
    SELECT conname, pg_get_constraintdef(c.oid)
    FROM pg_constraint c
    JOIN pg_namespace n ON n.oid = c.connamespace
    WHERE contype = 'c' 
    AND conrelid = 'tbl_miembro'::regclass
");
$constraints = $stmt->fetchAll();
if (empty($constraints)) {
    echo "  - No CHECK constraints found on tbl_miembro.\n";
} else {
    echo "  - Constraints found:\n";
    print_r($constraints);
}

// Checking the CHECK constraint on tbl_estado_miembro.id_estado_miembro
echo "\n4. Checking constraints on tbl_estado_miembro:\n";
$stmt = $pdo->query("
    SELECT conname, pg_get_constraintdef(c.oid)
    FROM pg_constraint c
    WHERE conrelid = 'tbl_estado_miembro'::regclass AND contype = 'c'
");
$estado_constraints = $stmt->fetchAll();
print_r($estado_constraints);

echo "\nDONE.\n";

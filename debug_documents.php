<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Crear la conexión a la base de datos
$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'sqlsrv',
    'host' => '10.16.15.10',
    'port' => '1433',
    'database' => 'ServerLobos',
    'username' => 'sistemas_fer',
    'password' => '#Dodgers040800',
    'charset' => 'utf8',
    'prefix' => '',
    'options' => [
        'encrypt' => true,
        'TrustServerCertificate' => true,
    ]
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== DOCUMENTOS CON aplica_area_salud = 0 (FISCALES) ===\n";
$fiscales = Capsule::table('sug_documentos')
    ->where('activo', true)
    ->where('aplica_area_salud', 0)
    ->get();

echo "Total documentos fiscales: " . $fiscales->count() . "\n\n";

foreach ($fiscales as $doc) {
    echo "ID: {$doc->id} | Nombre: {$doc->nombre} | aplica_area_salud: {$doc->aplica_area_salud} | activo: {$doc->activo}\n";
}

echo "\n=== DOCUMENTOS CON aplica_area_salud = 1 (MÉDICOS) ===\n";
$medicos = Capsule::table('sug_documentos')
    ->where('activo', true)
    ->where('aplica_area_salud', 1)
    ->get();

echo "Total documentos médicos: " . $medicos->count() . "\n\n";

foreach ($medicos as $doc) {
    echo "ID: {$doc->id} | Nombre: {$doc->nombre} | aplica_area_salud: {$doc->aplica_area_salud} | activo: {$doc->activo}\n";
}

echo "\n=== TODOS LOS DOCUMENTOS ACTIVOS ===\n";
$todos = Capsule::table('sug_documentos')
    ->where('activo', true)
    ->get();

echo "Total documentos activos: " . $todos->count() . "\n\n";

foreach ($todos as $doc) {
    echo "ID: {$doc->id} | Nombre: {$doc->nombre} | aplica_area_salud: {$doc->aplica_area_salud} | activo: {$doc->activo}\n";
}

?>

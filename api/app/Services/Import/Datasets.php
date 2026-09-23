<?php

namespace App\Services\Import;

/**
 * Catálogo de tablas importables: campos destino, obligatorios y clave de upsert.
 * Los nombres de campo coinciden con los encabezados de la plantilla CSV.
 */
class Datasets
{
    public static function all(): array
    {
        return [
            'items' => [
                'label' => 'Productos', 'icon' => 'box', 'desc' => 'SKU, descripción, UM, categoría, mín/máx, clase ABC', 'key' => 'sku',
                'fields' => ['sku' => true, 'descripcion' => true, 'um' => true, 'categoria' => false, 'subcategoria' => false, 'cod_fabrica' => false, 'minimo' => false, 'maximo' => false, 'clase_abc' => false, 'vida_util_dias' => false, 'maneja_lote' => false, 'maneja_vencimiento' => false, 'peso_kg' => false, 'precio' => false, 'estado' => false],
                'sample' => "sku,descripcion,um,categoria,subcategoria,minimo,maximo,clase_abc,maneja_lote\n5T2010101001,BOLSA 8 X 12 ECO COLORES,BUL,Bolsas,Eco,40,400,A,1\n5T2010101002,BOLSA 11 X 14 ECO COLORES,BUL,Bolsas,Eco,40,400,A,1",
            ],
            'customers' => [
                'label' => 'Clientes', 'icon' => 'users', 'desc' => 'Código, razón social, NIT, dirección, zona, canal', 'key' => 'codigo',
                'fields' => ['codigo' => true, 'razon_social' => true, 'nit' => false, 'contacto' => false, 'telefono' => false, 'direccion' => false, 'departamento' => false, 'provincia' => false, 'ciudad' => false, 'zona' => false, 'canal' => false, 'estado' => false],
                'sample' => "codigo,razon_social,nit,ciudad,departamento,telefono,canal\nB0100004,FERRETERIA TAROPE,,Trinidad,Beni,72033118,Ferretería\nS0200011,SUPERMERCADOS HIPERMAXI,1028374012,Santa Cruz,Santa Cruz,33456700,Cadena",
            ],
            'locations' => [
                'label' => 'Ubicaciones', 'icon' => 'map', 'desc' => 'Rack/zona, código, columna, nivel, capacidad, orden de recorrido', 'key' => 'codigo',
                'fields' => ['codigo' => true, 'rack' => true, 'columna' => false, 'nivel' => false, 'tipo_zona' => false, 'orden' => false, 'peso_max_kg' => false, 'permite_mezcla' => false, 'estado' => false],
                'sample' => "codigo,rack,columna,nivel,tipo_zona,orden\nE7-C01-N1,E7,1,1,ALMACENAJE,1\nE7-C01-N2,E7,1,2,ALMACENAJE,2\nE7-C02-N1,E7,2,1,ALMACENAJE,4",
            ],
            'drivers' => [
                'label' => 'Choferes', 'icon' => 'users', 'desc' => 'CI, nombre, apellidos, licencia, teléfono', 'key' => 'ci',
                'fields' => ['ci' => true, 'nombre' => true, 'apellidos' => true, 'licencia' => false, 'telefono' => false, 'direccion' => false, 'estado' => false],
                'sample' => "ci,nombre,apellidos,licencia,telefono\n5891192,Fredy,Rivero Padilla,CAT-C,78912233\n6242792,Omar,Ponce,CAT-C,72479200",
            ],
            'vehicles' => [
                'label' => 'Camiones', 'icon' => 'truck', 'desc' => 'Placa, tipo, marca, capacidad, chofer habitual (CI)', 'key' => 'placa',
                'fields' => ['placa' => true, 'tipo' => true, 'marca' => false, 'modelo' => false, 'capacidad_kg' => false, 'volumen_m3' => false, 'chofer_ci' => false, 'estado' => false],
                'sample' => "placa,tipo,marca,capacidad_kg,volumen_m3\n2384-KHT,Camión 8 t,Hino 500,8000,42\n4102-LPD,Camión 12 t,Volvo VM,12000,60",
            ],
            'users_collector' => [
                'label' => 'Usuarios colector', 'icon' => 'phone', 'desc' => 'Cuenta, nombre, rol, almacén, PIN', 'key' => 'usuario',
                'fields' => ['usuario' => true, 'nombre' => true, 'rol' => true, 'almacen' => true, 'pin' => false, 'documento' => false, 'estado' => false],
                'sample' => "usuario,nombre,rol,almacen,pin\npgarcia,Pedro Garcia Ortiz,SUPERVISOR,BOLSAS,1234\nrsuarez,Ronaldo Suarez Dorado,OPERADOR,BOLSAS,4321",
            ],
            'users_desktop' => [
                'label' => 'Usuarios escritorio', 'icon' => 'shield', 'desc' => 'Cuenta, nombre, correo, rol, almacenes (separados por |), contraseña inicial', 'key' => 'usuario',
                'fields' => ['usuario' => true, 'nombre' => true, 'rol' => true, 'almacenes' => true, 'correo' => false, 'password' => false, 'estado' => false],
                'sample' => "usuario,nombre,correo,rol,almacenes\njguasace,Juan Pablo Guasace,jguasace@plasticoscarmen.com,ADMIN,BOLSAS\nfrivero,Fredy Rivero,frivero@plasticoscarmen.com,SUPERVISOR,BOLSAS|MP",
            ],
        ];
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function required(string $id): array
    {
        return array_keys(array_filter(self::all()[$id]['fields'] ?? []));
    }
}

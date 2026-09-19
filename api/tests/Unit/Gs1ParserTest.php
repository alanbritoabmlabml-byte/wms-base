<?php

namespace Tests\Unit;

use App\Support\Gs1Parser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Gs1ParserTest extends TestCase
{
    private Gs1Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Gs1Parser;
    }

    #[Test]
    public function parsea_un_gs1_concatenado_de_01_17_y_10(): void
    {
        // (01) GTIN-14 y (17) vencimiento son de largo fijo; (10) lote es variable
        // y va ultimo, asi que no necesita separador.
        $code = '01'.'07501234567890'.'17'.'270331'.'10'.'L2609A';

        $parsed = $this->parser->parse($code);

        $this->assertSame('07501234567890', $parsed['01']);
        $this->assertSame('2027-03-31', $parsed['17']);
        $this->assertSame('L2609A', $parsed['10']);
    }

    #[Test]
    public function respeta_el_separador_fnc1_en_campos_de_largo_variable(): void
    {
        $code = '01'.'07501234567890'.'10'.'L2609A'.Gs1Parser::FNC1.'37'.'00012'.Gs1Parser::FNC1.'21'.'SERIE-9';

        $parsed = $this->parser->parse($code);

        $this->assertSame('07501234567890', $parsed['01']);
        $this->assertSame('L2609A', $parsed['10']);
        $this->assertSame('12', $parsed['37']);
        $this->assertSame('SERIE-9', $parsed['21']);
        $this->assertSame(12.0, $this->parser->quantity($parsed));
    }

    #[Test]
    public function parsea_sscc_fecha_de_fabricacion_y_cantidad_de_unidades(): void
    {
        $code = '00'.'123456789012345678'.'11'.'260115'.'30'.'240'.Gs1Parser::FNC1;

        $parsed = $this->parser->parse($code);

        $this->assertSame('123456789012345678', $parsed['00']);
        $this->assertSame('2026-01-15', $parsed['11']);
        $this->assertSame('240', $parsed['30']);
    }

    #[Test]
    public function aplica_el_decimal_implicito_de_los_ai_310n(): void
    {
        // 3103 = peso neto en kg con 3 decimales: 012500 -> 12.500 kg
        $parsed = $this->parser->parse('01'.'07501234567890'.'3103'.'012500');

        $this->assertSame('12.500', $parsed['3103']);
        $this->assertSame(12.5, $this->parser->quantity($parsed));
    }

    #[Test]
    public function acepta_la_forma_legible_con_parentesis(): void
    {
        $parsed = $this->parser->parse('(01)07501234567890(17)270331(10)L2609A');

        $this->assertSame('07501234567890', $parsed['01']);
        $this->assertSame('2027-03-31', $parsed['17']);
        $this->assertSame('L2609A', $parsed['10']);
    }

    #[Test]
    public function el_dia_00_significa_fin_de_mes(): void
    {
        $parsed = $this->parser->parse('17'.'270200');

        $this->assertSame('2027-02-28', $parsed['17']);
    }

    #[Test]
    public function distingue_un_gs1_de_un_codigo_cualquiera(): void
    {
        $this->assertTrue(Gs1Parser::looksLikeGs1('(01)07501234567890'));
        $this->assertTrue(Gs1Parser::looksLikeGs1('01'.'07501234567890'.Gs1Parser::FNC1.'10ABC'));
        $this->assertFalse(Gs1Parser::looksLikeGs1('E1A-C01-N03'));
        $this->assertFalse(Gs1Parser::looksLikeGs1('5T2010201559'));
    }
}

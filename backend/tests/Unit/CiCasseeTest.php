<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// SCRUM-29 · Test volontairement cassé pour vérifier que la CI bloque la fusion.
// Cette PR doit être FERMÉE sans être fusionnée.
class CiCasseeTest extends TestCase
{
    public function test_la_ci_doit_echouer(): void
    {
        $this->assertTrue(false, 'Échec volontaire (SCRUM-29)');
    }
}
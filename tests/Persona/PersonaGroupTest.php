<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Persona;

use Guild\DevTools\Persona\PersonaGroup;
use Guild\Framework\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonaGroup::class)]
final class PersonaGroupTest extends TestCase
{
    public function testTheIdentifierAndLabelAreTrimmed(): void
    {
        $group = new PersonaGroup(' iu:roles:sys:acm:starter-editors ', ' Starter Editors ');

        self::assertSame('iu:roles:sys:acm:starter-editors', $group->identifier, 'the identifier is what grants match on');
        self::assertSame('Starter Editors', $group->label, 'the label is what the System Admin check matches on');
    }

    public function testABlankIdentifierIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("A persona group has a blank identifier. Give it the group's Grouper system name, e.g. 'iu:roles:sys:acm:starter-editors'.");

        new PersonaGroup(' ', 'Starter Editors');
    }

    public function testABlankLabelIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Persona group 'iu:x' has a blank label. Give it the group's ACM label, e.g. 'Starter Editors'.");

        new PersonaGroup('iu:x', '');
    }

    public function testItBecomesTheGroupGrouperWouldReturn(): void
    {
        $grouper = new PersonaGroup('iu:x', 'X Editors')->toGrouperGroup();

        self::assertSame('iu:x', $grouper->identifier, 'grants match on the identifier');
        self::assertSame('X Editors', $grouper->displayExtension, 'the System Admin check matches on displayExtension');
        self::assertSame('X Editors', $grouper->displayName, 'the label stands in for the display path');
        self::assertSame(md5('iu:x'), $grouper->uuid, 'a stable uuid derived from the identifier');
    }
}

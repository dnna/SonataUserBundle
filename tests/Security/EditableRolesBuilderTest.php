<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\UserBundle\Tests\Security\Authorization\Voter;

use PHPUnit\Framework\TestCase;
use Sonata\UserBundle\Security\EditableRolesBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EditableRolesBuilderTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testRolesFromHierarchy(): void
    {
        $token = $this->createMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->any())->method('getToken')->willReturn($token);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->any())->method('isGranted')->willReturn(true);

        $pool = $this->getMockBuilder('Sonata\AdminBundle\Admin\Pool')
                ->disableOriginalConstructor()
                ->getMock();

        $pool->expects($this->exactly(2))->method('getAdminServiceIds')->willReturn([]);

        $rolesHierarchy = [
            'ROLE_ADMIN' => [
                0 => 'ROLE_USER',
            ],
            'ROLE_SUPER_ADMIN' => [
                0 => 'ROLE_USER',
                1 => 'ROLE_SONATA_ADMIN',
                2 => 'ROLE_ADMIN',
                3 => 'ROLE_ALLOWED_TO_SWITCH',
                4 => 'ROLE_SONATA_PAGE_ADMIN_PAGE_EDIT',
                5 => 'ROLE_SONATA_PAGE_ADMIN_BLOCK_EDIT',
            ],
            'SONATA' => [],
        ];

        $expected = [
            'ROLE_ADMIN' => 'ROLE_ADMIN: ROLE_USER',
            'ROLE_USER' => 'ROLE_USER',
            'ROLE_SUPER_ADMIN' => 'ROLE_SUPER_ADMIN: ROLE_USER, ROLE_SONATA_ADMIN, ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH, ROLE_SONATA_PAGE_ADMIN_PAGE_EDIT, ROLE_SONATA_PAGE_ADMIN_BLOCK_EDIT',
            'ROLE_SONATA_ADMIN' => 'ROLE_SONATA_ADMIN',
            'ROLE_ALLOWED_TO_SWITCH' => 'ROLE_ALLOWED_TO_SWITCH',
            'ROLE_SONATA_PAGE_ADMIN_PAGE_EDIT' => 'ROLE_SONATA_PAGE_ADMIN_PAGE_EDIT',
            'ROLE_SONATA_PAGE_ADMIN_BLOCK_EDIT' => 'ROLE_SONATA_PAGE_ADMIN_BLOCK_EDIT',
            'SONATA' => 'SONATA: ',
        ];

        $builder = new EditableRolesBuilder($tokenStorage, $authorizationChecker, $pool, $rolesHierarchy);
        $roles = $builder->getRoles();
        $rolesReadOnly = $builder->getRolesReadOnly();

        $this->assertEmpty($rolesReadOnly);
        $this->assertSame($expected, $roles);
    }

    public function testRolesFromAdminWithMasterAdmin(): void
    {
        $securityHandler = $this->createMock('Sonata\AdminBundle\Security\Handler\SecurityHandlerInterface');
        $securityHandler->expects($this->exactly(2))->method('getBaseRole')->willReturn('ROLE_FOO_%s');

        $admin = $this->createMock('Sonata\AdminBundle\Admin\AdminInterface');
        $admin->expects($this->exactly(2))->method('isGranted')->willReturn(true);
        $admin->expects($this->exactly(2))->method('getSecurityInformation')->willReturn(['GUEST' => [0 => 'VIEW', 1 => 'LIST'], 'STAFF' => [0 => 'EDIT', 1 => 'LIST', 2 => 'CREATE'], 'EDITOR' => [0 => 'OPERATOR', 1 => 'EXPORT'], 'ADMIN' => [0 => 'MASTER']]);
        $admin->expects($this->exactly(2))->method('getSecurityHandler')->willReturn($securityHandler);

        $token = $this->createMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->any())->method('getToken')->willReturn($token);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->any())->method('isGranted')->willReturn(true);

        $pool = $this->getMockBuilder('Sonata\AdminBundle\Admin\Pool')
                ->disableOriginalConstructor()
                ->getMock();

        $pool->expects($this->exactly(2))->method('getInstance')->willReturn($admin);
        $pool->expects($this->exactly(2))->method('getAdminServiceIds')->willReturn(['myadmin']);

        $builder = new EditableRolesBuilder($tokenStorage, $authorizationChecker, $pool, []);

        $expected = [
          'ROLE_FOO_GUEST' => 'ROLE_FOO_GUEST',
          'ROLE_FOO_STAFF' => 'ROLE_FOO_STAFF',
          'ROLE_FOO_EDITOR' => 'ROLE_FOO_EDITOR',
          'ROLE_FOO_ADMIN' => 'ROLE_FOO_ADMIN',
        ];

        $roles = $builder->getRoles();
        $rolesReadOnly = $builder->getRolesReadOnly();
        $this->assertEmpty($rolesReadOnly);
        $this->assertSame($expected, $roles);
    }

    public function testWithNoSecurityToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->any())->method('getToken')->willReturn(null);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->any())->method('isGranted')->willReturn(false);

        $pool = $this->getMockBuilder('Sonata\AdminBundle\Admin\Pool')
                ->disableOriginalConstructor()
                ->getMock();

        $builder = new EditableRolesBuilder($tokenStorage, $authorizationChecker, $pool, []);

        $roles = $builder->getRoles();
        $rolesReadOnly = $builder->getRolesReadOnly();

        $this->assertEmpty($roles);
        $this->assertEmpty($rolesReadOnly);
    }
}

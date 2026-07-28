<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\ApplicationBusyException;
use App\Service\AttachmentMutationLock;
use App\Service\FileLockManager;
use App\Service\TimerMutationLock;
use PHPUnit\Framework\TestCase;

final class FileLockManagerTest extends TestCase
{
    public function testNonBlockingLockReturnsNullWhileExclusiveLockIsHeld(): void
    {
        $path = sys_get_temp_dir().'/studio-commesse-lock-'.bin2hex(random_bytes(6)).'.lock';
        $first = new FileLockManager($path);
        $second = new FileLockManager($path);
        $exclusive = $first->acquireExclusive();

        try {
            self::assertNull($second->tryAcquireShared());
        } finally {
            $exclusive->release();
        }

        $shared = $second->tryAcquireShared();
        self::assertNotNull($shared);
        $shared?->release();
        @unlink($path);
    }

    public function testMutationLocksFailFastWhenAnExclusiveOperationIsAlreadyRunning(): void
    {
        $path = sys_get_temp_dir().'/studio-commesse-busy-lock-'.bin2hex(random_bytes(6)).'.lock';
        $exclusive = (new FileLockManager($path))->acquireExclusive();

        try {
            try {
                (new TimerMutationLock($path))->acquireExclusive();
                self::fail('La modifica concorrente delle ore avrebbe dovuto essere rifiutata.');
            } catch (ApplicationBusyException) {
                self::addToAssertionCount(1);
            }

            try {
                (new AttachmentMutationLock($path))->acquireShared();
                self::fail('La modifica documentale durante un lock esclusivo avrebbe dovuto essere rifiutata.');
            } catch (ApplicationBusyException) {
                self::addToAssertionCount(1);
            }
        } finally {
            $exclusive->release();
            @unlink($path);
        }
    }

}

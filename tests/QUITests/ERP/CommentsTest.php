<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Comments;
use QUI\Interfaces\Users\User as UserInterface;
use ReflectionProperty;

class CommentsTest extends TestCase
{
    public function testConstructorNormalizesAndFiltersStoredComments(): void
    {
        $Comments = new Comments([
            'invalid',
            ['message' => 'missing time'],
            ['time' => 10],
            ['message' => 'valid', 'time' => 20, 'id' => 'known-id']
        ]);

        self::assertSame([[
            'message' => 'valid',
            'time' => 20,
            'id' => 'known-id'
        ]], $Comments->toArray());
    }

    public function testAddCommentSanitizesUnsafeMarkupAndSerializesMetadata(): void
    {
        $Comments = new Comments();
        $Comments->addComment(
            '<script>alert(1)</script><strong>Visible</strong>',
            123,
            'quiqqer/erp',
            'fa fa-file',
            'comment-id',
            'object-hash'
        );

        $expected = [[
            'message' => 'alert(1)<strong>Visible</strong>',
            'time' => 123,
            'source' => 'quiqqer/erp',
            'sourceIcon' => 'fa fa-file',
            'id' => 'comment-id',
            'objectHash' => 'object-hash'
        ]];

        self::assertFalse($Comments->isEmpty());
        self::assertSame($expected, $Comments->toArray());
        self::assertSame(json_encode($expected), $Comments->serialize());
        self::assertSame($Comments->serialize(), $Comments->toJSON());
    }

    public function testUnserializeHandlesJsonArraysAndInvalidJson(): void
    {
        $json = json_encode([[
            'message' => 'Stored',
            'time' => 100,
            'id' => 'stored-id'
        ]], JSON_THROW_ON_ERROR);

        self::assertSame('Stored', Comments::unserialize($json)->toArray()[0]['message']);
        self::assertTrue(Comments::unserialize('{invalid json')->isEmpty());
        self::assertTrue(Comments::unserialize([])->isEmpty());
    }

    public function testImportSortsCommentsAndSuppliesOptionalMetadata(): void
    {
        $Target = new Comments([[
            'message' => 'Later',
            'time' => 200,
            'id' => 'later'
        ]]);
        $Source = new Comments([[
            'message' => 'Earlier',
            'time' => 100,
            'id' => 'earlier',
            'objectHash' => 'source-object'
        ]]);

        $Target->import($Source);

        self::assertSame(['Earlier', 'Later'], array_column($Target->toArray(), 'message'));
        self::assertSame('', $Target->toArray()[0]['source']);
        self::assertSame('source-object', $Target->toArray()[0]['objectHash']);

        $Target->clear();
        self::assertTrue($Target->isEmpty());
    }

    public function testDefaultCommentMetadataIsGeneratedAndEqualTimesRemainValid(): void
    {
        $Comments = new Comments();
        $Comments->addComment('Generated metadata');
        $generated = $Comments->toArray()[0];

        self::assertGreaterThan(0, $generated['time']);
        self::assertIsString($generated['id']);
        self::assertNotSame('', $generated['id']);

        $EqualTimes = new Comments([
            ['message' => 'First', 'time' => 42, 'id' => 'first'],
            ['message' => 'Second', 'time' => 42, 'id' => 'second']
        ]);
        $EqualTimes->sort();
        self::assertSame(['First', 'Second'], array_column($EqualTimes->toArray(), 'message'));
    }

    public function testUserCommentsAndHistoryFireExtensionEventsAndPreserveEditability(): void
    {
        $originalEvents = QUI::$Events;
        $Events = $this->createMock(QUI\Events\Manager::class);
        $Events->expects(self::exactly(2))->method('fireEvent')->withAnyParameters();
        QUI::$Events = $Events;

        $PermissionUser = new ReflectionProperty(QUI\Permissions\Permission::class, 'User');
        $originalPermissionUser = $PermissionUser->getValue();
        $SystemUser = $this->createMock(QUI\Users\SystemUser::class);
        $SystemUser->method('isSU')->willReturn(true);
        $PermissionUser->setValue(null, $SystemUser);

        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->with('comments')->willReturn(json_encode([
            [
                'message' => 'Later customer note',
                'time' => 200,
                'id' => 'customer-note',
                'source' => 'quiqqer/customer'
            ],
            [
                'message' => 'Earlier customer note',
                'time' => 100,
                'id' => 'plain-note'
            ]
        ], JSON_THROW_ON_ERROR));

        try {
            $comments = Comments::getCommentsByUser($User)->toArray();
            $HistoryUser = $this->createMock(QUI\Users\User::class);
            $history = Comments::getHistoryByUser($HistoryUser);
        } finally {
            $PermissionUser->setValue(null, $originalPermissionUser);
            QUI::$Events = $originalEvents;
        }

        self::assertSame(['Earlier customer note', 'Later customer note'], array_column($comments, 'message'));
        self::assertTrue($comments[1]['editable']);
        self::assertFalse($comments[0]['editable'] ?? false);
        self::assertTrue($history->isEmpty());
    }
}

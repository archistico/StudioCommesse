<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case LoginSucceeded = 'security.login_succeeded';
    case LoginFailed = 'security.login_failed';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case ClientCreated = 'client.created';
    case ClientUpdated = 'client.updated';
    case ClientArchived = 'client.archived';
    case ClientRestored = 'client.restored';
    case ProjectCreated = 'project.created';
    case ProjectUpdated = 'project.updated';
    case ProjectArchived = 'project.archived';
    case ProjectRestored = 'project.restored';
    case ActivityCreated = 'activity.created';
    case ActivityUpdated = 'activity.updated';
    case TimeEntryCreated = 'time_entry.created';
    case TimerStarted = 'timer.started';
    case TimerStopped = 'timer.stopped';
    case ExpenseCreated = 'expense.created';
    case ExpenseUpdated = 'expense.updated';
    case ExpenseDeleted = 'expense.deleted';
    case PaymentCreated = 'payment.created';
    case PaymentUpdated = 'payment.updated';
    case PaymentDeleted = 'payment.deleted';
    case AttachmentUploaded = 'attachment.uploaded';
    case AttachmentUpdated = 'attachment.updated';
    case AttachmentDownloaded = 'attachment.downloaded';
    case AttachmentDeleted = 'attachment.deleted';
    case FixturesLoaded = 'fixtures.loaded';
}

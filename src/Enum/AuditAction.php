<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case LoginSucceeded = 'security.login_succeeded';
    case LoginFailed = 'security.login_failed';
    case LoginThrottled = 'security.login_throttled';
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
    case TimeEntryUpdated = 'time_entry.updated';
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

    public function label(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Accesso riuscito',
            self::LoginFailed => 'Accesso non riuscito',
            self::LoginThrottled => 'Accesso temporaneamente bloccato',
            self::UserCreated => 'Utente creato',
            self::UserUpdated => 'Utente aggiornato',
            self::ClientCreated => 'Cliente creato',
            self::ClientUpdated => 'Cliente aggiornato',
            self::ClientArchived => 'Cliente archiviato',
            self::ClientRestored => 'Cliente ripristinato',
            self::ProjectCreated => 'Commessa creata',
            self::ProjectUpdated => 'Commessa aggiornata',
            self::ProjectArchived => 'Commessa archiviata',
            self::ProjectRestored => 'Commessa ripristinata',
            self::ActivityCreated => 'Attività creata',
            self::ActivityUpdated => 'Attività aggiornata',
            self::TimeEntryCreated => 'Ore registrate',
            self::TimeEntryUpdated => 'Registrazione ore aggiornata',
            self::TimerStarted => 'Timer avviato',
            self::TimerStopped => 'Timer fermato',
            self::ExpenseCreated => 'Spesa registrata',
            self::ExpenseUpdated => 'Spesa aggiornata',
            self::ExpenseDeleted => 'Spesa eliminata',
            self::PaymentCreated => 'Incasso registrato',
            self::PaymentUpdated => 'Incasso aggiornato',
            self::PaymentDeleted => 'Incasso eliminato',
            self::AttachmentUploaded => 'Documento caricato',
            self::AttachmentUpdated => 'Documento aggiornato',
            self::AttachmentDownloaded => 'Documento scaricato',
            self::AttachmentDeleted => 'Documento eliminato',
            self::FixturesLoaded => 'Fixtures caricate',
        };
    }

    public function groupLabel(): string
    {
        return match ($this) {
            self::LoginSucceeded, self::LoginFailed, self::LoginThrottled => 'Sicurezza',
            self::UserCreated, self::UserUpdated => 'Utenti',
            self::ClientCreated, self::ClientUpdated, self::ClientArchived, self::ClientRestored => 'Clienti',
            self::ProjectCreated, self::ProjectUpdated, self::ProjectArchived, self::ProjectRestored => 'Commesse',
            self::ActivityCreated, self::ActivityUpdated => 'Attività',
            self::TimeEntryCreated, self::TimeEntryUpdated, self::TimerStarted, self::TimerStopped => 'Ore',
            self::ExpenseCreated, self::ExpenseUpdated, self::ExpenseDeleted,
            self::PaymentCreated, self::PaymentUpdated, self::PaymentDeleted => 'Economia',
            self::AttachmentUploaded, self::AttachmentUpdated, self::AttachmentDownloaded, self::AttachmentDeleted => 'Documenti',
            self::FixturesLoaded => 'Sistema',
        };
    }
}

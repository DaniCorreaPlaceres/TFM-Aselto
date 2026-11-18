<?php

namespace App\Shared\Domain;

enum SecurityEvent: string
{
    case LoginSucceeded = 'authentication.succeeded';
    case LoginFailed = 'authentication.failed';
    case AuthenticationRejected = 'authentication.rejected';
    case AuthenticationLimited = 'authentication.limited';
    case AccessDenied = 'authorization.denied';
    case ResourceLimited = 'resource.limited';
    case Logout = 'session.logout';
    case SessionExpired = 'session.expired';
    case RecoveryRequested = 'password.recovery_requested';
    case PasswordChanged = 'password.changed';
    case AccountActivated = 'account.activated';
    case FirmChanged = 'administration.firm_changed';
    case ClientChanged = 'administration.client_changed';
    case EmailChangeRequested = 'administration.email_change_requested';
    case EmailChanged = 'administration.email_changed';
    case EmailChangeCancelled = 'administration.email_change_cancelled';
    case InvitationCreated = 'administration.invitation_created';
    case InvitationResent = 'administration.invitation_resent';
    case InvitationAccepted = 'administration.invitation_accepted';
    case LicenseAssigned = 'administration.license_assigned';
    case JournalRead = 'audit.read';
    case RetentionApplied = 'audit.retention_applied';
    case AlertSent = 'audit.alert_sent';
    case AlertFailed = 'audit.alert_failed';

    public function result(): string
    {
        return match ($this) {
            self::LoginFailed, self::AuthenticationRejected, self::AccessDenied, self::AlertFailed => 'failure',
            self::AuthenticationLimited, self::ResourceLimited => 'limited',
            self::RecoveryRequested, self::EmailChangeRequested => 'requested',
            default => 'success',
        };
    }
}

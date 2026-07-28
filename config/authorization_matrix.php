<?php

declare(strict_types=1);

/**
 * Matrice autoritativa delle rotte applicative.
 *
 * access:
 * - public: accesso senza autenticazione;
 * - collaborator: qualsiasi utente attivo autenticato;
 * - partner: esclusivamente ROLE_PARTNER;
 * - owner_or_partner: proprietario del dato oppure socio;
 * - project_responsible_or_partner: responsabile della commessa oppure socio;
 * - activity_editor_or_partner: assegnatario, autore, responsabile o socio;
 * - attachment_manager_or_partner: uploader, responsabile, assegnatario/autore attività o socio.
 *
 * archive:
 * - read: consultazione consentita anche per commesse archiviate;
 * - deny_write: operazione di scrittura vietata su commesse archiviate;
 * - not_applicable: nessuna commessa coinvolta.
 *
 * @return array<string, array{methods: list<string>, access: string, ownership: string, archive: string, data: string}>
 */
return [
    'app_home' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'redirect dashboard'],
    'app_login' => ['methods' => ['GET', 'POST'], 'access' => 'public', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'authentication only'],
    'app_logout' => ['methods' => ['POST'], 'access' => 'collaborator', 'ownership' => 'current user', 'archive' => 'not_applicable', 'data' => 'session only'],
    'app_dashboard' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'read', 'data' => 'operational'],

    'app_client_index' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'read', 'data' => 'economic columns partner only'],
    'app_client_new' => ['methods' => ['GET', 'POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'client administration'],
    'app_client_show' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'read', 'data' => 'operational'],
    'app_client_edit' => ['methods' => ['GET', 'POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'deny_write', 'data' => 'client administration'],
    'app_client_archive' => ['methods' => ['POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'client administration'],
    'app_client_restore' => ['methods' => ['POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'client administration'],

    'app_project_index' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'read', 'data' => 'operational'],
    'app_project_new' => ['methods' => ['GET', 'POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'operational and economic'],
    'app_project_show' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'private note responsible or partner', 'archive' => 'read', 'data' => 'financial closure partner only'],
    'app_project_edit' => ['methods' => ['GET', 'POST'], 'access' => 'project_responsible_or_partner', 'ownership' => 'ProjectVoter::EDIT', 'archive' => 'deny_write', 'data' => 'administrative fields partner only'],
    'app_project_archive' => ['methods' => ['POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'project administration'],
    'app_project_restore' => ['methods' => ['POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'project administration'],

    'app_activity_index' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'assignee filter', 'archive' => 'read', 'data' => 'operational'],
    'app_activity_new' => ['methods' => ['GET', 'POST'], 'access' => 'collaborator', 'ownership' => 'current user becomes creator', 'archive' => 'deny_write', 'data' => 'financial override partner only'],
    'app_activity_edit' => ['methods' => ['GET', 'POST'], 'access' => 'activity_editor_or_partner', 'ownership' => 'assignee, creator or project responsible', 'archive' => 'deny_write', 'data' => 'financial override partner only'],

    'app_time_entry_index' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'read', 'data' => 'no rates or costs'],
    'app_time_entry_new' => ['methods' => ['GET', 'POST'], 'access' => 'collaborator', 'ownership' => 'current user only', 'archive' => 'deny_write', 'data' => 'rate snapshot server-side'],
    'app_activity_time' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'read', 'data' => 'rates and costs partner only'],
    'app_timer_start' => ['methods' => ['POST'], 'access' => 'collaborator', 'ownership' => 'current user only', 'archive' => 'deny_write', 'data' => 'CSRF protected'],
    'app_timer_stop' => ['methods' => ['POST'], 'access' => 'collaborator', 'ownership' => 'current user running timer', 'archive' => 'deny_write', 'data' => 'CSRF protected'],
    'app_time_entry_edit' => ['methods' => ['GET', 'POST'], 'access' => 'owner_or_partner', 'ownership' => 'entry owner or partner', 'archive' => 'deny_write', 'data' => 'owner cannot change user or rate snapshot'],

    'app_economics_index' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'full economic'],
    'app_economics_project' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'collaborator sees own expenses only', 'archive' => 'read', 'data' => 'full economic partner only'],
    'app_expense_new' => ['methods' => ['GET', 'POST'], 'access' => 'collaborator', 'ownership' => 'recordedBy forced to current user', 'archive' => 'deny_write', 'data' => 'own expense'],
    'app_expense_edit' => ['methods' => ['GET', 'POST'], 'access' => 'owner_or_partner', 'ownership' => 'ExpenseVoter::MANAGE', 'archive' => 'deny_write', 'data' => 'own expense or partner'],
    'app_expense_delete' => ['methods' => ['POST'], 'access' => 'owner_or_partner', 'ownership' => 'ExpenseVoter::MANAGE', 'archive' => 'deny_write', 'data' => 'CSRF protected'],
    'app_payment_new' => ['methods' => ['GET', 'POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'deny_write', 'data' => 'full economic'],
    'app_payment_edit' => ['methods' => ['GET', 'POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'deny_write', 'data' => 'full economic'],
    'app_payment_delete' => ['methods' => ['POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'deny_write', 'data' => 'CSRF protected'],

    'app_attachment_index' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'none', 'archive' => 'read', 'data' => 'metadata'],
    'app_attachment_project' => ['methods' => ['GET', 'POST'], 'access' => 'collaborator', 'ownership' => 'uploader current user', 'archive' => 'deny_write', 'data' => 'metadata and protected upload'],
    'app_attachment_show' => ['methods' => ['GET', 'POST'], 'access' => 'collaborator', 'ownership' => 'AttachmentVoter::VIEW/MANAGE', 'archive' => 'deny_write', 'data' => 'metadata'],
    'app_attachment_download' => ['methods' => ['GET'], 'access' => 'collaborator', 'ownership' => 'AttachmentVoter::VIEW', 'archive' => 'read', 'data' => 'protected binary'],
    'app_attachment_delete' => ['methods' => ['POST'], 'access' => 'attachment_manager_or_partner', 'ownership' => 'AttachmentVoter::MANAGE', 'archive' => 'deny_write', 'data' => 'CSRF protected'],

    'app_control_index' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'operational and economic'],
    'app_control_collaborator_show' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'person evaluation'],
    'app_monthly_report' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'operational and economic'],
    'app_monthly_report_csv' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'operational and economic CSV'],
    'app_monthly_report_users_csv' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'aggregated user cost CSV'],

    'app_audit_index' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'security and operational audit'],
    'app_audit_csv' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'read', 'data' => 'security and operational audit CSV'],

    'app_user_index' => ['methods' => ['GET'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'user administration'],
    'app_user_new' => ['methods' => ['GET', 'POST'], 'access' => 'partner', 'ownership' => 'none', 'archive' => 'not_applicable', 'data' => 'user administration'],
    'app_user_edit' => ['methods' => ['GET', 'POST'], 'access' => 'partner', 'ownership' => 'guarded last partner/self update', 'archive' => 'not_applicable', 'data' => 'user administration'],
];

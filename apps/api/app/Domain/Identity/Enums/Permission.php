<?php

namespace App\Domain\Identity\Enums;

/**
 * Granular permissions. Single source of truth: the seeder syncs this list to
 * the `permissions` table and policies reference these cases, never strings.
 */
enum Permission: string
{
    // Users & access control
    case UsersViewAny = 'users.view_any';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDeactivate = 'users.deactivate';
    case UsersAssignRoles = 'users.assign_roles';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case AuditLogsView = 'audit_logs.view';
    case SettingsManage = 'settings.manage';

    // Agency & agents
    case AgencyManage = 'agency.manage';
    case AgentsViewAny = 'agents.view_any';
    case AgentsManage = 'agents.manage';

    // Properties
    case PropertiesViewAny = 'properties.view_any';
    case PropertiesCreate = 'properties.create';
    case PropertiesUpdateAny = 'properties.update_any';
    case PropertiesUpdateOwn = 'properties.update_own';
    case PropertiesDelete = 'properties.delete';
    case PropertiesPublish = 'properties.publish';
    case SellerListingsView = 'seller.listings.view';

    // MLS integration engine
    case MlsView = 'mls.view';
    case MlsManage = 'mls.manage';
    case MlsSync = 'mls.sync';

    // CRM
    case LeadsViewAny = 'leads.view_any';
    case LeadsViewAssigned = 'leads.view_assigned';
    case LeadsCreate = 'leads.create';
    case LeadsUpdateAny = 'leads.update_any';
    case LeadsUpdateAssigned = 'leads.update_assigned';
    case LeadsAssign = 'leads.assign';

    // Showings
    case AppointmentsViewAny = 'appointments.view_any';
    case AppointmentsManageAny = 'appointments.manage_any';
    case AppointmentsManageOwn = 'appointments.manage_own';
    case AppointmentsRequest = 'appointments.request';

    // Offers & transactions
    case OffersViewAny = 'offers.view_any';
    case OffersManageAny = 'offers.manage_any';
    case OffersManageOwn = 'offers.manage_own';
    case TransactionsViewAny = 'transactions.view_any';
    case TransactionsManageAny = 'transactions.manage_any';
    case TransactionsManageOwn = 'transactions.manage_own';

    // Documents
    case DocumentsViewAny = 'documents.view_any';
    case DocumentsManageAny = 'documents.manage_any';
    case DocumentsManageOwn = 'documents.manage_own';
    case DocumentsViewShared = 'documents.view_shared';

    // Engagement
    case MessagesUse = 'messages.use';
    case FavoritesManage = 'favorites.manage';
    case SavedSearchesManage = 'saved_searches.manage';

    // Reporting
    case ReportsView = 'reports.view';
    case AnalyticsView = 'analytics.view';

    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}

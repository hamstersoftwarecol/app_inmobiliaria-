<?php

namespace App\Domain\Identity\Enums;

/**
 * System roles. The level establishes a strict hierarchy that prevents
 * privilege escalation: a user can only manage users/roles strictly below
 * their own highest level (super admins excepted).
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Broker = 'broker';
    case OfficeManager = 'office_manager';
    case Agent = 'agent';
    case Seller = 'seller';
    case Buyer = 'buyer';

    public function level(): int
    {
        return match ($this) {
            self::SuperAdmin => 100,
            self::Broker => 80,
            self::OfficeManager => 60,
            self::Agent => 40,
            self::Seller => 20,
            self::Buyer => 10,
        };
    }

    public function displayName(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Broker => 'Broker / Agency Owner',
            self::OfficeManager => 'Office Manager',
            self::Agent => 'Real Estate Agent',
            self::Seller => 'Seller / Property Owner',
            self::Buyer => 'Buyer / Tenant',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Full platform administration, MLS integrations, security and global settings.',
            self::Broker => 'Manages the agency, its agents, listings, pipeline, transactions and statistics.',
            self::OfficeManager => 'Runs daily operations: assigns clients, schedules showings, supervises agents.',
            self::Agent => 'Works assigned clients, listings, showings, offers and transaction documents.',
            self::Seller => 'Follows linked properties, shared documents and transaction status.',
            self::Buyer => 'Searches properties, saves favorites and alerts, requests showings.',
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Broker, self::OfficeManager], true);
    }

    /**
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Broker => array_values(array_filter(
                Permission::cases(),
                fn (Permission $p) => ! in_array($p, [
                    Permission::RolesManage,
                    Permission::SettingsManage,
                    Permission::MlsManage,
                    Permission::MlsSync,
                ], true),
            )),
            self::OfficeManager => [
                Permission::UsersViewAny,
                Permission::AgentsViewAny,
                Permission::PropertiesViewAny,
                Permission::LeadsViewAny,
                Permission::LeadsCreate,
                Permission::LeadsUpdateAny,
                Permission::LeadsAssign,
                Permission::AppointmentsViewAny,
                Permission::AppointmentsManageAny,
                Permission::OffersViewAny,
                Permission::TransactionsViewAny,
                Permission::DocumentsViewAny,
                Permission::DocumentsManageAny,
                Permission::ReportsView,
                Permission::MessagesUse,
            ],
            self::Agent => [
                Permission::AgentsManageOwnProfile,
                Permission::PropertiesCreate,
                Permission::PropertiesUpdateOwn,
                Permission::LeadsViewAssigned,
                Permission::LeadsCreate,
                Permission::LeadsUpdateAssigned,
                Permission::AppointmentsManageOwn,
                Permission::OffersManageOwn,
                Permission::TransactionsManageOwn,
                Permission::DocumentsManageOwn,
                Permission::MessagesUse,
            ],
            self::Seller => [
                Permission::SellerListingsView,
                Permission::DocumentsViewShared,
                Permission::FavoritesManage,
                Permission::MessagesUse,
            ],
            self::Buyer => [
                Permission::FavoritesManage,
                Permission::SavedSearchesManage,
                Permission::AppointmentsRequest,
                Permission::DocumentsViewShared,
                Permission::MessagesUse,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Services\AdminRoleService;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Create permissions
            $permissions = $this->createPermissions();
            
            // Create roles
            $roles = $this->createRoles();
            
            // Assign permissions to roles
            $this->assignPermissionsToRoles($roles, $permissions);
            
            // Migrate existing admin users
            $this->migrateExistingAdmins($roles);
            
            DB::commit();
            $this->command->info('Roles and permissions seeded successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to seed roles and permissions: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create all permissions from inventory document
     */
    private function createPermissions(): array
    {
        $permissions = [];

        // Dashboard permissions
        $permissions['dashboard.view'] = Permission::firstOrCreate(
            ['slug' => 'dashboard.view'],
            ['name' => 'View Dashboard', 'module' => 'dashboard', 'description' => 'View dashboard statistics']
        );
        $permissions['dashboard.export'] = Permission::firstOrCreate(
            ['slug' => 'dashboard.export'],
            ['name' => 'Export Dashboard Data', 'module' => 'dashboard', 'description' => 'Export dashboard data']
        );

        // Buyers Management permissions
        $permissions['buyers.view'] = Permission::firstOrCreate(
            ['slug' => 'buyers.view'],
            ['name' => 'View Buyers', 'module' => 'buyers', 'description' => 'View customer list']
        );
        $permissions['buyers.view_details'] = Permission::firstOrCreate(
            ['slug' => 'buyers.view_details'],
            ['name' => 'View Buyer Details', 'module' => 'buyers', 'description' => 'View customer details']
        );
        $permissions['buyers.edit'] = Permission::firstOrCreate(
            ['slug' => 'buyers.edit'],
            ['name' => 'Edit Buyers', 'module' => 'buyers', 'description' => 'Edit customer information']
        );
        $permissions['buyers.delete'] = Permission::firstOrCreate(
            ['slug' => 'buyers.delete'],
            ['name' => 'Delete Buyers', 'module' => 'buyers', 'description' => 'Delete/deactivate customers']
        );
        $permissions['buyers.export'] = Permission::firstOrCreate(
            ['slug' => 'buyers.export'],
            ['name' => 'Export Buyer Data', 'module' => 'buyers', 'description' => 'Export customer data']
        );

        // Buyer Orders permissions
        $permissions['buyer_orders.view'] = Permission::firstOrCreate(
            ['slug' => 'buyer_orders.view'],
            ['name' => 'View Buyer Orders', 'module' => 'orders', 'description' => 'View buyer orders']
        );
        $permissions['buyer_orders.view_details'] = Permission::firstOrCreate(
            ['slug' => 'buyer_orders.view_details'],
            ['name' => 'View Buyer Order Details', 'module' => 'orders', 'description' => 'View order details']
        );
        $permissions['buyer_orders.update_status'] = Permission::firstOrCreate(
            ['slug' => 'buyer_orders.update_status'],
            ['name' => 'Update Buyer Order Status', 'module' => 'orders', 'description' => 'Update order status']
        );
        $permissions['buyer_orders.cancel'] = Permission::firstOrCreate(
            ['slug' => 'buyer_orders.cancel'],
            ['name' => 'Cancel Buyer Orders', 'module' => 'orders', 'description' => 'Cancel orders']
        );
        $permissions['buyer_orders.refund'] = Permission::firstOrCreate(
            ['slug' => 'buyer_orders.refund'],
            ['name' => 'Refund Buyer Orders', 'module' => 'orders', 'description' => 'Process refunds']
        );
        $permissions['buyer_orders.export'] = Permission::firstOrCreate(
            ['slug' => 'buyer_orders.export'],
            ['name' => 'Export Buyer Order Data', 'module' => 'orders', 'description' => 'Export order data']
        );

        // Buyer Transactions permissions
        $permissions['buyer_transactions.view'] = Permission::firstOrCreate(
            ['slug' => 'buyer_transactions.view'],
            ['name' => 'View Buyer Transactions', 'module' => 'transactions', 'description' => 'View transactions']
        );
        $permissions['buyer_transactions.view_details'] = Permission::firstOrCreate(
            ['slug' => 'buyer_transactions.view_details'],
            ['name' => 'View Buyer Transaction Details', 'module' => 'transactions', 'description' => 'View transaction details']
        );
        $permissions['buyer_transactions.export'] = Permission::firstOrCreate(
            ['slug' => 'buyer_transactions.export'],
            ['name' => 'Export Buyer Transaction Data', 'module' => 'transactions', 'description' => 'Export transaction data']
        );
        $permissions['buyer_transactions.refund'] = Permission::firstOrCreate(
            ['slug' => 'buyer_transactions.refund'],
            ['name' => 'Refund Buyer Transactions', 'module' => 'transactions', 'description' => 'Process refunds']
        );

        // Sellers Management permissions
        $permissions['sellers.view'] = Permission::firstOrCreate(
            ['slug' => 'sellers.view'],
            ['name' => 'View Sellers', 'module' => 'sellers', 'description' => 'View stores list']
        );
        $permissions['sellers.view_details'] = Permission::firstOrCreate(
            ['slug' => 'sellers.view_details'],
            ['name' => 'View Seller Details', 'module' => 'sellers', 'description' => 'View store details']
        );
        $permissions['sellers.edit'] = Permission::firstOrCreate(
            ['slug' => 'sellers.edit'],
            ['name' => 'Edit Sellers', 'module' => 'sellers', 'description' => 'Edit store information']
        );
        $permissions['sellers.suspend'] = Permission::firstOrCreate(
            ['slug' => 'sellers.suspend'],
            ['name' => 'Suspend Sellers', 'module' => 'sellers', 'description' => 'Suspend stores']
        );
        $permissions['sellers.activate'] = Permission::firstOrCreate(
            ['slug' => 'sellers.activate'],
            ['name' => 'Activate Sellers', 'module' => 'sellers', 'description' => 'Activate stores']
        );
        $permissions['sellers.delete'] = Permission::firstOrCreate(
            ['slug' => 'sellers.delete'],
            ['name' => 'Delete Sellers', 'module' => 'sellers', 'description' => 'Delete stores']
        );
        $permissions['sellers.export'] = Permission::firstOrCreate(
            ['slug' => 'sellers.export'],
            ['name' => 'Export Seller Data', 'module' => 'sellers', 'description' => 'Export store data']
        );

        // Seller Orders permissions
        $permissions['seller_orders.view'] = Permission::firstOrCreate(
            ['slug' => 'seller_orders.view'],
            ['name' => 'View Seller Orders', 'module' => 'orders', 'description' => 'View seller orders']
        );
        $permissions['seller_orders.view_details'] = Permission::firstOrCreate(
            ['slug' => 'seller_orders.view_details'],
            ['name' => 'View Seller Order Details', 'module' => 'orders', 'description' => 'View order details']
        );
        $permissions['seller_orders.update_status'] = Permission::firstOrCreate(
            ['slug' => 'seller_orders.update_status'],
            ['name' => 'Update Seller Order Status', 'module' => 'orders', 'description' => 'Update order status']
        );
        $permissions['seller_orders.export'] = Permission::firstOrCreate(
            ['slug' => 'seller_orders.export'],
            ['name' => 'Export Seller Order Data', 'module' => 'orders', 'description' => 'Export order data']
        );

        // Seller Transactions permissions
        $permissions['seller_transactions.view'] = Permission::firstOrCreate(
            ['slug' => 'seller_transactions.view'],
            ['name' => 'View Seller Transactions', 'module' => 'transactions', 'description' => 'View transactions']
        );
        $permissions['seller_transactions.view_details'] = Permission::firstOrCreate(
            ['slug' => 'seller_transactions.view_details'],
            ['name' => 'View Seller Transaction Details', 'module' => 'transactions', 'description' => 'View transaction details']
        );
        $permissions['seller_transactions.approve_payout'] = Permission::firstOrCreate(
            ['slug' => 'seller_transactions.approve_payout'],
            ['name' => 'Approve Seller Payouts', 'module' => 'transactions', 'description' => 'Approve payouts']
        );
        $permissions['seller_transactions.reject_payout'] = Permission::firstOrCreate(
            ['slug' => 'seller_transactions.reject_payout'],
            ['name' => 'Reject Seller Payouts', 'module' => 'transactions', 'description' => 'Reject payouts']
        );
        $permissions['seller_transactions.export'] = Permission::firstOrCreate(
            ['slug' => 'seller_transactions.export'],
            ['name' => 'Export Seller Transaction Data', 'module' => 'transactions', 'description' => 'Export transaction data']
        );

        // Products/Services permissions
        $permissions['products.view'] = Permission::firstOrCreate(
            ['slug' => 'products.view'],
            ['name' => 'View Products', 'module' => 'products', 'description' => 'View products/services']
        );
        $permissions['products.view_details'] = Permission::firstOrCreate(
            ['slug' => 'products.view_details'],
            ['name' => 'View Product Details', 'module' => 'products', 'description' => 'View product details']
        );
        $permissions['products.edit'] = Permission::firstOrCreate(
            ['slug' => 'products.edit'],
            ['name' => 'Edit Products', 'module' => 'products', 'description' => 'Edit products']
        );
        $permissions['products.approve'] = Permission::firstOrCreate(
            ['slug' => 'products.approve'],
            ['name' => 'Approve Products', 'module' => 'products', 'description' => 'Approve products']
        );
        $permissions['products.reject'] = Permission::firstOrCreate(
            ['slug' => 'products.reject'],
            ['name' => 'Reject Products', 'module' => 'products', 'description' => 'Reject products']
        );
        $permissions['products.delete'] = Permission::firstOrCreate(
            ['slug' => 'products.delete'],
            ['name' => 'Delete Products', 'module' => 'products', 'description' => 'Delete products']
        );
        $permissions['products.boost'] = Permission::firstOrCreate(
            ['slug' => 'products.boost'],
            ['name' => 'Boost Products', 'module' => 'products', 'description' => 'Boost product visibility']
        );
        $permissions['products.export'] = Permission::firstOrCreate(
            ['slug' => 'products.export'],
            ['name' => 'Export Product Data', 'module' => 'products', 'description' => 'Export product data']
        );

        // KYC permissions
        $permissions['kyc.view'] = Permission::firstOrCreate(
            ['slug' => 'kyc.view'],
            ['name' => 'View KYC Requests', 'module' => 'kyc', 'description' => 'View KYC requests']
        );
        $permissions['kyc.view_details'] = Permission::firstOrCreate(
            ['slug' => 'kyc.view_details'],
            ['name' => 'View KYC Details', 'module' => 'kyc', 'description' => 'View KYC details']
        );
        $permissions['kyc.approve'] = Permission::firstOrCreate(
            ['slug' => 'kyc.approve'],
            ['name' => 'Approve KYC', 'module' => 'kyc', 'description' => 'Approve KYC']
        );
        $permissions['kyc.reject'] = Permission::firstOrCreate(
            ['slug' => 'kyc.reject'],
            ['name' => 'Reject KYC', 'module' => 'kyc', 'description' => 'Reject KYC']
        );
        $permissions['kyc.request_changes'] = Permission::firstOrCreate(
            ['slug' => 'kyc.request_changes'],
            ['name' => 'Request KYC Changes', 'module' => 'kyc', 'description' => 'Request KYC changes']
        );

        // Subscriptions permissions
        $permissions['subscriptions.view'] = Permission::firstOrCreate(
            ['slug' => 'subscriptions.view'],
            ['name' => 'View Subscriptions', 'module' => 'subscriptions', 'description' => 'View subscriptions']
        );
        $permissions['subscriptions.view_details'] = Permission::firstOrCreate(
            ['slug' => 'subscriptions.view_details'],
            ['name' => 'View Subscription Details', 'module' => 'subscriptions', 'description' => 'View subscription details']
        );
        $permissions['subscriptions.create_plan'] = Permission::firstOrCreate(
            ['slug' => 'subscriptions.create_plan'],
            ['name' => 'Create Subscription Plans', 'module' => 'subscriptions', 'description' => 'Create subscription plans']
        );
        $permissions['subscriptions.edit_plan'] = Permission::firstOrCreate(
            ['slug' => 'subscriptions.edit_plan'],
            ['name' => 'Edit Subscription Plans', 'module' => 'subscriptions', 'description' => 'Edit subscription plans']
        );
        $permissions['subscriptions.delete_plan'] = Permission::firstOrCreate(
            ['slug' => 'subscriptions.delete_plan'],
            ['name' => 'Delete Subscription Plans', 'module' => 'subscriptions', 'description' => 'Delete subscription plans']
        );
        $permissions['subscriptions.manage'] = Permission::firstOrCreate(
            ['slug' => 'subscriptions.manage'],
            ['name' => 'Manage User Subscriptions', 'module' => 'subscriptions', 'description' => 'Manage user subscriptions']
        );

        // Promotions permissions
        $permissions['promotions.view'] = Permission::firstOrCreate(
            ['slug' => 'promotions.view'],
            ['name' => 'View Promotions', 'module' => 'promotions', 'description' => 'View promotions']
        );
        $permissions['promotions.view_details'] = Permission::firstOrCreate(
            ['slug' => 'promotions.view_details'],
            ['name' => 'View Promotion Details', 'module' => 'promotions', 'description' => 'View promotion details']
        );
        $permissions['promotions.create'] = Permission::firstOrCreate(
            ['slug' => 'promotions.create'],
            ['name' => 'Create Promotions', 'module' => 'promotions', 'description' => 'Create promotions']
        );
        $permissions['promotions.edit'] = Permission::firstOrCreate(
            ['slug' => 'promotions.edit'],
            ['name' => 'Edit Promotions', 'module' => 'promotions', 'description' => 'Edit promotions']
        );
        $permissions['promotions.approve'] = Permission::firstOrCreate(
            ['slug' => 'promotions.approve'],
            ['name' => 'Approve Promotions', 'module' => 'promotions', 'description' => 'Approve promotions']
        );
        $permissions['promotions.reject'] = Permission::firstOrCreate(
            ['slug' => 'promotions.reject'],
            ['name' => 'Reject Promotions', 'module' => 'promotions', 'description' => 'Reject promotions']
        );
        $permissions['promotions.delete'] = Permission::firstOrCreate(
            ['slug' => 'promotions.delete'],
            ['name' => 'Delete Promotions', 'module' => 'promotions', 'description' => 'Delete promotions']
        );

        // Social Feed permissions
        $permissions['social_feed.view'] = Permission::firstOrCreate(
            ['slug' => 'social_feed.view'],
            ['name' => 'View Social Feed', 'module' => 'social_feed', 'description' => 'View social feed']
        );
        $permissions['social_feed.view_details'] = Permission::firstOrCreate(
            ['slug' => 'social_feed.view_details'],
            ['name' => 'View Post Details', 'module' => 'social_feed', 'description' => 'View post details']
        );
        $permissions['social_feed.approve'] = Permission::firstOrCreate(
            ['slug' => 'social_feed.approve'],
            ['name' => 'Approve Posts', 'module' => 'social_feed', 'description' => 'Approve posts']
        );
        $permissions['social_feed.reject'] = Permission::firstOrCreate(
            ['slug' => 'social_feed.reject'],
            ['name' => 'Reject Posts', 'module' => 'social_feed', 'description' => 'Reject posts']
        );
        $permissions['social_feed.delete'] = Permission::firstOrCreate(
            ['slug' => 'social_feed.delete'],
            ['name' => 'Delete Posts', 'module' => 'social_feed', 'description' => 'Delete posts']
        );
        $permissions['social_feed.pin'] = Permission::firstOrCreate(
            ['slug' => 'social_feed.pin'],
            ['name' => 'Pin Posts', 'module' => 'social_feed', 'description' => 'Pin posts']
        );

        // All Users permissions
        $permissions['all_users.view'] = Permission::firstOrCreate(
            ['slug' => 'all_users.view'],
            ['name' => 'View All Users', 'module' => 'all_users', 'description' => 'View all users']
        );
        $permissions['all_users.view_details'] = Permission::firstOrCreate(
            ['slug' => 'all_users.view_details'],
            ['name' => 'View User Details', 'module' => 'all_users', 'description' => 'View user details']
        );
        $permissions['all_users.edit'] = Permission::firstOrCreate(
            ['slug' => 'all_users.edit'],
            ['name' => 'Edit Users', 'module' => 'all_users', 'description' => 'Edit user information']
        );
        $permissions['all_users.delete'] = Permission::firstOrCreate(
            ['slug' => 'all_users.delete'],
            ['name' => 'Delete Users', 'module' => 'all_users', 'description' => 'Delete users']
        );
        $permissions['all_users.export'] = Permission::firstOrCreate(
            ['slug' => 'all_users.export'],
            ['name' => 'Export User Data', 'module' => 'all_users', 'description' => 'Export user data']
        );

        // Balance permissions
        $permissions['balance.view'] = Permission::firstOrCreate(
            ['slug' => 'balance.view'],
            ['name' => 'View Balances', 'module' => 'balance', 'description' => 'View balances']
        );
        $permissions['balance.view_details'] = Permission::firstOrCreate(
            ['slug' => 'balance.view_details'],
            ['name' => 'View Balance Details', 'module' => 'balance', 'description' => 'View balance details']
        );
        $permissions['balance.adjust'] = Permission::firstOrCreate(
            ['slug' => 'balance.adjust'],
            ['name' => 'Adjust Balances', 'module' => 'balance', 'description' => 'Adjust user balances']
        );
        $permissions['balance.export'] = Permission::firstOrCreate(
            ['slug' => 'balance.export'],
            ['name' => 'Export Balance Data', 'module' => 'balance', 'description' => 'Export balance data']
        );

        // Chats permissions
        $permissions['chats.view'] = Permission::firstOrCreate(
            ['slug' => 'chats.view'],
            ['name' => 'View Chats', 'module' => 'chats', 'description' => 'View chats']
        );
        $permissions['chats.view_details'] = Permission::firstOrCreate(
            ['slug' => 'chats.view_details'],
            ['name' => 'View Chat Details', 'module' => 'chats', 'description' => 'View chat details']
        );
        $permissions['chats.delete'] = Permission::firstOrCreate(
            ['slug' => 'chats.delete'],
            ['name' => 'Delete Chats', 'module' => 'chats', 'description' => 'Delete chats']
        );
        $permissions['chats.export'] = Permission::firstOrCreate(
            ['slug' => 'chats.export'],
            ['name' => 'Export Chat Data', 'module' => 'chats', 'description' => 'Export chat data']
        );

        // Analytics permissions
        $permissions['analytics.view'] = Permission::firstOrCreate(
            ['slug' => 'analytics.view'],
            ['name' => 'View Analytics', 'module' => 'analytics', 'description' => 'View analytics']
        );
        $permissions['analytics.export'] = Permission::firstOrCreate(
            ['slug' => 'analytics.export'],
            ['name' => 'Export Analytics Data', 'module' => 'analytics', 'description' => 'Export analytics data']
        );
        $permissions['analytics.custom_reports'] = Permission::firstOrCreate(
            ['slug' => 'analytics.custom_reports'],
            ['name' => 'Create Custom Reports', 'module' => 'analytics', 'description' => 'Create custom reports']
        );

        // Leaderboard permissions
        $permissions['leaderboard.view'] = Permission::firstOrCreate(
            ['slug' => 'leaderboard.view'],
            ['name' => 'View Leaderboard', 'module' => 'leaderboard', 'description' => 'View leaderboard']
        );
        $permissions['leaderboard.export'] = Permission::firstOrCreate(
            ['slug' => 'leaderboard.export'],
            ['name' => 'Export Leaderboard Data', 'module' => 'leaderboard', 'description' => 'Export leaderboard data']
        );

        // Support permissions
        $permissions['support.view'] = Permission::firstOrCreate(
            ['slug' => 'support.view'],
            ['name' => 'View Support Tickets', 'module' => 'support', 'description' => 'View support tickets']
        );
        $permissions['support.view_details'] = Permission::firstOrCreate(
            ['slug' => 'support.view_details'],
            ['name' => 'View Ticket Details', 'module' => 'support', 'description' => 'View ticket details']
        );
        $permissions['support.assign'] = Permission::firstOrCreate(
            ['slug' => 'support.assign'],
            ['name' => 'Assign Tickets', 'module' => 'support', 'description' => 'Assign tickets']
        );
        $permissions['support.resolve'] = Permission::firstOrCreate(
            ['slug' => 'support.resolve'],
            ['name' => 'Resolve Tickets', 'module' => 'support', 'description' => 'Resolve tickets']
        );
        $permissions['support.close'] = Permission::firstOrCreate(
            ['slug' => 'support.close'],
            ['name' => 'Close Tickets', 'module' => 'support', 'description' => 'Close tickets']
        );
        $permissions['support.export'] = Permission::firstOrCreate(
            ['slug' => 'support.export'],
            ['name' => 'Export Ticket Data', 'module' => 'support', 'description' => 'Export ticket data']
        );

        // Disputes permissions
        $permissions['disputes.view'] = Permission::firstOrCreate(
            ['slug' => 'disputes.view'],
            ['name' => 'View Disputes', 'module' => 'disputes', 'description' => 'View disputes']
        );
        $permissions['disputes.view_details'] = Permission::firstOrCreate(
            ['slug' => 'disputes.view_details'],
            ['name' => 'View Dispute Details', 'module' => 'disputes', 'description' => 'View dispute details']
        );
        $permissions['disputes.resolve'] = Permission::firstOrCreate(
            ['slug' => 'disputes.resolve'],
            ['name' => 'Resolve Disputes', 'module' => 'disputes', 'description' => 'Resolve disputes']
        );
        $permissions['disputes.escalate'] = Permission::firstOrCreate(
            ['slug' => 'disputes.escalate'],
            ['name' => 'Escalate Disputes', 'module' => 'disputes', 'description' => 'Escalate disputes']
        );
        $permissions['disputes.export'] = Permission::firstOrCreate(
            ['slug' => 'disputes.export'],
            ['name' => 'Export Dispute Data', 'module' => 'disputes', 'description' => 'Export dispute data']
        );

        // Withdrawals permissions
        $permissions['withdrawals.view'] = Permission::firstOrCreate(
            ['slug' => 'withdrawals.view'],
            ['name' => 'View Withdrawal Requests', 'module' => 'withdrawals', 'description' => 'View withdrawal requests']
        );
        $permissions['withdrawals.view_details'] = Permission::firstOrCreate(
            ['slug' => 'withdrawals.view_details'],
            ['name' => 'View Withdrawal Details', 'module' => 'withdrawals', 'description' => 'View withdrawal details']
        );
        $permissions['withdrawals.approve'] = Permission::firstOrCreate(
            ['slug' => 'withdrawals.approve'],
            ['name' => 'Approve Withdrawals', 'module' => 'withdrawals', 'description' => 'Approve withdrawals']
        );
        $permissions['withdrawals.reject'] = Permission::firstOrCreate(
            ['slug' => 'withdrawals.reject'],
            ['name' => 'Reject Withdrawals', 'module' => 'withdrawals', 'description' => 'Reject withdrawals']
        );
        $permissions['withdrawals.export'] = Permission::firstOrCreate(
            ['slug' => 'withdrawals.export'],
            ['name' => 'Export Withdrawal Data', 'module' => 'withdrawals', 'description' => 'Export withdrawal data']
        );

        // Ratings & Reviews permissions
        $permissions['ratings.view'] = Permission::firstOrCreate(
            ['slug' => 'ratings.view'],
            ['name' => 'View Ratings/Reviews', 'module' => 'ratings', 'description' => 'View ratings/reviews']
        );
        $permissions['ratings.view_details'] = Permission::firstOrCreate(
            ['slug' => 'ratings.view_details'],
            ['name' => 'View Review Details', 'module' => 'ratings', 'description' => 'View review details']
        );
        $permissions['ratings.approve'] = Permission::firstOrCreate(
            ['slug' => 'ratings.approve'],
            ['name' => 'Approve Reviews', 'module' => 'ratings', 'description' => 'Approve reviews']
        );
        $permissions['ratings.reject'] = Permission::firstOrCreate(
            ['slug' => 'ratings.reject'],
            ['name' => 'Reject Reviews', 'module' => 'ratings', 'description' => 'Reject reviews']
        );
        $permissions['ratings.delete'] = Permission::firstOrCreate(
            ['slug' => 'ratings.delete'],
            ['name' => 'Delete Reviews', 'module' => 'ratings', 'description' => 'Delete reviews']
        );
        $permissions['ratings.export'] = Permission::firstOrCreate(
            ['slug' => 'ratings.export'],
            ['name' => 'Export Review Data', 'module' => 'ratings', 'description' => 'Export review data']
        );

        // Referrals permissions
        $permissions['referrals.view'] = Permission::firstOrCreate(
            ['slug' => 'referrals.view'],
            ['name' => 'View Referral Data', 'module' => 'referrals', 'description' => 'View referral data']
        );
        $permissions['referrals.view_details'] = Permission::firstOrCreate(
            ['slug' => 'referrals.view_details'],
            ['name' => 'View Referral Details', 'module' => 'referrals', 'description' => 'View referral details']
        );
        $permissions['referrals.settings'] = Permission::firstOrCreate(
            ['slug' => 'referrals.settings'],
            ['name' => 'Manage Referral Settings', 'module' => 'referrals', 'description' => 'Manage referral settings']
        );
        $permissions['referrals.export'] = Permission::firstOrCreate(
            ['slug' => 'referrals.export'],
            ['name' => 'Export Referral Data', 'module' => 'referrals', 'description' => 'Export referral data']
        );

        // Notifications permissions
        $permissions['notifications.view'] = Permission::firstOrCreate(
            ['slug' => 'notifications.view'],
            ['name' => 'View Notifications', 'module' => 'notifications', 'description' => 'View notifications']
        );
        $permissions['notifications.create'] = Permission::firstOrCreate(
            ['slug' => 'notifications.create'],
            ['name' => 'Create Notifications', 'module' => 'notifications', 'description' => 'Create notifications']
        );
        $permissions['notifications.edit'] = Permission::firstOrCreate(
            ['slug' => 'notifications.edit'],
            ['name' => 'Edit Notifications', 'module' => 'notifications', 'description' => 'Edit notifications']
        );
        $permissions['notifications.delete'] = Permission::firstOrCreate(
            ['slug' => 'notifications.delete'],
            ['name' => 'Delete Notifications', 'module' => 'notifications', 'description' => 'Delete notifications']
        );
        $permissions['notifications.send'] = Permission::firstOrCreate(
            ['slug' => 'notifications.send'],
            ['name' => 'Send Notifications', 'module' => 'notifications', 'description' => 'Send notifications']
        );

        // Seller Help permissions
        $permissions['seller_help.view'] = Permission::firstOrCreate(
            ['slug' => 'seller_help.view'],
            ['name' => 'View Help Requests', 'module' => 'seller_help', 'description' => 'View help requests']
        );
        $permissions['seller_help.view_details'] = Permission::firstOrCreate(
            ['slug' => 'seller_help.view_details'],
            ['name' => 'View Request Details', 'module' => 'seller_help', 'description' => 'View request details']
        );
        $permissions['seller_help.resolve'] = Permission::firstOrCreate(
            ['slug' => 'seller_help.resolve'],
            ['name' => 'Resolve Help Requests', 'module' => 'seller_help', 'description' => 'Resolve help requests']
        );
        $permissions['seller_help.export'] = Permission::firstOrCreate(
            ['slug' => 'seller_help.export'],
            ['name' => 'Export Request Data', 'module' => 'seller_help', 'description' => 'Export request data']
        );

        // Settings permissions
        $permissions['settings.view'] = Permission::firstOrCreate(
            ['slug' => 'settings.view'],
            ['name' => 'View Settings', 'module' => 'settings', 'description' => 'View settings']
        );
        $permissions['settings.admin_management'] = Permission::firstOrCreate(
            ['slug' => 'settings.admin_management'],
            ['name' => 'Manage Admins', 'module' => 'settings', 'description' => 'Manage admins']
        );
        $permissions['settings.create_admin'] = Permission::firstOrCreate(
            ['slug' => 'settings.create_admin'],
            ['name' => 'Create Admin Users', 'module' => 'settings', 'description' => 'Create admin users']
        );
        $permissions['settings.edit_admin'] = Permission::firstOrCreate(
            ['slug' => 'settings.edit_admin'],
            ['name' => 'Edit Admin Users', 'module' => 'settings', 'description' => 'Edit admin users']
        );
        $permissions['settings.delete_admin'] = Permission::firstOrCreate(
            ['slug' => 'settings.delete_admin'],
            ['name' => 'Delete Admin Users', 'module' => 'settings', 'description' => 'Delete admin users']
        );
        $permissions['settings.faq_management'] = Permission::firstOrCreate(
            ['slug' => 'settings.faq_management'],
            ['name' => 'Manage FAQs', 'module' => 'settings', 'description' => 'Manage FAQs']
        );
        $permissions['settings.categories_management'] = Permission::firstOrCreate(
            ['slug' => 'settings.categories_management'],
            ['name' => 'Manage Categories', 'module' => 'settings', 'description' => 'Manage categories']
        );
        $permissions['settings.brands_management'] = Permission::firstOrCreate(
            ['slug' => 'settings.brands_management'],
            ['name' => 'Manage Brands', 'module' => 'settings', 'description' => 'Manage brands']
        );
        $permissions['settings.knowledge_base'] = Permission::firstOrCreate(
            ['slug' => 'settings.knowledge_base'],
            ['name' => 'Manage Knowledge Base', 'module' => 'settings', 'description' => 'Manage knowledge base']
        );
        $permissions['settings.terms_management'] = Permission::firstOrCreate(
            ['slug' => 'settings.terms_management'],
            ['name' => 'Manage Terms & Conditions', 'module' => 'settings', 'description' => 'Manage terms & conditions']
        );
        $permissions['settings.system_config'] = Permission::firstOrCreate(
            ['slug' => 'settings.system_config'],
            ['name' => 'Manage System Configuration', 'module' => 'settings', 'description' => 'Manage system configuration']
        );

        return $permissions;
    }

    /**
     * Create default roles
     */
    private function createRoles(): array
    {
        $roles = [];

        // Super Admin - Full access
        $roles['super_admin'] = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'is_active' => true,
            ]
        );

        // Admin - Full operational access except system settings
        $roles['admin'] = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Full operational access except system settings',
                'is_active' => true,
            ]
        );

        // Moderator - Content moderation and support
        $roles['moderator'] = Role::firstOrCreate(
            ['slug' => 'moderator'],
            [
                'name' => 'Moderator',
                'description' => 'Content moderation and support',
                'is_active' => true,
            ]
        );

        // Support Agent - Customer support focused
        $roles['support_agent'] = Role::firstOrCreate(
            ['slug' => 'support_agent'],
            [
                'name' => 'Support Agent',
                'description' => 'Customer support focused',
                'is_active' => true,
            ]
        );

        // Financial Manager - Financial operations
        $roles['financial_manager'] = Role::firstOrCreate(
            ['slug' => 'financial_manager'],
            [
                'name' => 'Financial Manager',
                'description' => 'Financial operations',
                'is_active' => true,
            ]
        );

        // Content Manager - Content and product management
        $roles['content_manager'] = Role::firstOrCreate(
            ['slug' => 'content_manager'],
            [
                'name' => 'Content Manager',
                'description' => 'Content and product management',
                'is_active' => true,
            ]
        );

        return $roles;
    }

    /**
     * Assign permissions to roles based on hierarchy
     */
    private function assignPermissionsToRoles(array $roles, array $permissions): void
    {
        // Super Admin gets ALL permissions
        $roles['super_admin']->permissions()->sync(array_column($permissions, 'id'));

        // Admin gets all except system settings
        $adminPermissions = array_filter($permissions, function ($key) {
            return !str_starts_with($key, 'settings.');
        }, ARRAY_FILTER_USE_KEY);
        $roles['admin']->permissions()->sync(array_column($adminPermissions, 'id'));

        // Moderator permissions
        $moderatorPermissions = [
            'dashboard.view',
            'buyers.view', 'buyers.view_details',
            'sellers.view', 'sellers.view_details',
            'products.view', 'products.view_details', 'products.approve', 'products.reject',
            'kyc.view', 'kyc.view_details', 'kyc.approve', 'kyc.reject', 'kyc.request_changes',
            'promotions.view', 'promotions.view_details', 'promotions.approve', 'promotions.reject',
            'social_feed.view', 'social_feed.view_details', 'social_feed.approve', 'social_feed.reject', 'social_feed.delete', 'social_feed.pin',
            'ratings.view', 'ratings.view_details', 'ratings.approve', 'ratings.reject', 'ratings.delete',
            'support.view', 'support.view_details', 'support.assign', 'support.resolve', 'support.close',
            'disputes.view', 'disputes.view_details', 'disputes.resolve',
            'chats.view', 'chats.view_details',
        ];
        $moderatorPermissionIds = array_filter(array_map(function ($slug) use ($permissions) {
            return $permissions[$slug]->id ?? null;
        }, $moderatorPermissions));
        $roles['moderator']->permissions()->sync($moderatorPermissionIds);

        // Support Agent permissions
        $supportPermissions = [
            'dashboard.view',
            'support.view', 'support.view_details', 'support.assign', 'support.resolve', 'support.close', 'support.export',
            'chats.view', 'chats.view_details',
            'disputes.view', 'disputes.view_details',
            'buyer_orders.view', 'buyer_orders.view_details',
            'seller_orders.view', 'seller_orders.view_details',
        ];
        $supportPermissionIds = array_filter(array_map(function ($slug) use ($permissions) {
            return $permissions[$slug]->id ?? null;
        }, $supportPermissions));
        $roles['support_agent']->permissions()->sync($supportPermissionIds);

        // Financial Manager permissions
        $financialPermissions = [
            'dashboard.view', 'dashboard.export',
            'buyer_transactions.view', 'buyer_transactions.view_details', 'buyer_transactions.export', 'buyer_transactions.refund',
            'seller_transactions.view', 'seller_transactions.view_details', 'seller_transactions.approve_payout', 'seller_transactions.reject_payout', 'seller_transactions.export',
            'withdrawals.view', 'withdrawals.view_details', 'withdrawals.approve', 'withdrawals.reject', 'withdrawals.export',
            'balance.view', 'balance.view_details', 'balance.adjust', 'balance.export',
            'analytics.view', 'analytics.export',
        ];
        $financialPermissionIds = array_filter(array_map(function ($slug) use ($permissions) {
            return $permissions[$slug]->id ?? null;
        }, $financialPermissions));
        $roles['financial_manager']->permissions()->sync($financialPermissionIds);

        // Content Manager permissions
        $contentPermissions = [
            'dashboard.view',
            'products.view', 'products.view_details', 'products.edit', 'products.approve', 'products.reject', 'products.delete', 'products.boost', 'products.export',
            'promotions.view', 'promotions.view_details', 'promotions.create', 'promotions.edit', 'promotions.approve', 'promotions.reject', 'promotions.delete',
            'social_feed.view', 'social_feed.view_details', 'social_feed.approve', 'social_feed.reject', 'social_feed.delete', 'social_feed.pin',
            'ratings.view', 'ratings.view_details', 'ratings.approve', 'ratings.reject', 'ratings.delete', 'ratings.export',
            'settings.categories_management', 'settings.brands_management',
        ];
        $contentPermissionIds = array_filter(array_map(function ($slug) use ($permissions) {
            return $permissions[$slug]->id ?? null;
        }, $contentPermissions));
        $roles['content_manager']->permissions()->sync($contentPermissionIds);
    }

    /**
     * Migrate existing admin users to role system
     */
    private function migrateExistingAdmins(array $roles): void
    {
        $adminRoleService = new AdminRoleService();

        // Migrate super_admin users
        $superAdmins = \App\Models\User::where('role', 'super_admin')->get();
        foreach ($superAdmins as $user) {
            try {
                $adminRoleService->assignRole($user->id, $roles['super_admin']->id);
            } catch (\Exception $e) {
                $this->command->warn("Failed to migrate super_admin user {$user->id}: " . $e->getMessage());
            }
        }

        // Migrate admin users
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $user) {
            try {
                $adminRoleService->assignRole($user->id, $roles['admin']->id);
            } catch (\Exception $e) {
                $this->command->warn("Failed to migrate admin user {$user->id}: " . $e->getMessage());
            }
        }

        // Migrate moderator users
        $moderators = \App\Models\User::where('role', 'moderator')->get();
        foreach ($moderators as $user) {
            try {
                $adminRoleService->assignRole($user->id, $roles['moderator']->id);
            } catch (\Exception $e) {
                $this->command->warn("Failed to migrate moderator user {$user->id}: " . $e->getMessage());
            }
        }
    }
}

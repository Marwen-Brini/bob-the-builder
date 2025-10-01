<?php

declare(strict_types=1);

namespace Bob\Schema;

/**
 * WordPress Blueprint Extension
 *
 * Provides WordPress-specific schema helpers for creating WordPress/WooCommerce tables.
 */
class WordPressBlueprint extends Blueprint
{
    /**
     * Create WordPress ID column (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY)
     */
    public function wpId(string $column = 'ID'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create WordPress author column
     */
    public function wpAuthor(string $column = 'post_author'): ColumnDefinition
    {
        return $this->unsignedBigInteger($column)->default(0)->index();
    }

    /**
     * Create WordPress datetime columns
     */
    public function wpDatetime(string $column = 'post_date'): ColumnDefinition
    {
        return $this->dateTime($column)->default('0000-00-00 00:00:00');
    }

    /**
     * Create WordPress GMT datetime column
     */
    public function wpDatetimeGmt(string $column = 'post_date_gmt'): ColumnDefinition
    {
        return $this->dateTime($column)->default('0000-00-00 00:00:00');
    }

    /**
     * Add WordPress post date columns
     */
    public function wpDates(string $prefix = 'post'): void
    {
        $this->wpDatetime($prefix.'_date');
        $this->wpDatetimeGmt($prefix.'_date_gmt');
        $this->wpDatetime($prefix.'_modified');
        $this->wpDatetimeGmt($prefix.'_modified_gmt');
    }

    /**
     * Create WordPress title column
     */
    public function wpTitle(string $column = 'post_title'): ColumnDefinition
    {
        return $this->text($column);
    }

    /**
     * Create WordPress content column
     */
    public function wpContent(string $column = 'post_content'): ColumnDefinition
    {
        return $this->longText($column);
    }

    /**
     * Create WordPress excerpt column
     */
    public function wpExcerpt(string $column = 'post_excerpt'): ColumnDefinition
    {
        return $this->text($column);
    }

    /**
     * Create WordPress status column
     */
    public function wpStatus(string $column = 'post_status'): ColumnDefinition
    {
        return $this->string($column, 20)->default('publish')->index();
    }

    /**
     * Create WordPress slug column
     */
    public function wpSlug(string $column = 'post_name'): ColumnDefinition
    {
        return $this->string($column, 200)->default('')->index();
    }

    /**
     * Create WordPress GUID column
     */
    public function wpGuid(string $column = 'guid'): ColumnDefinition
    {
        return $this->string($column)->default('');
    }

    /**
     * Create WordPress menu order column
     */
    public function wpMenuOrder(string $column = 'menu_order'): ColumnDefinition
    {
        return $this->integer($column)->default(0);
    }

    /**
     * Create WordPress comment status column
     */
    public function wpCommentStatus(string $column = 'comment_status'): ColumnDefinition
    {
        return $this->string($column, 20)->default('open');
    }

    /**
     * Create WordPress ping status column
     */
    public function wpPingStatus(string $column = 'ping_status'): ColumnDefinition
    {
        return $this->string($column, 20)->default('open');
    }

    /**
     * Create WordPress password column
     */
    public function wpPassword(string $column = 'post_password'): ColumnDefinition
    {
        return $this->string($column)->default('');
    }

    /**
     * Create WordPress parent column
     */
    public function wpParent(string $column = 'post_parent'): ColumnDefinition
    {
        return $this->unsignedBigInteger($column)->default(0)->index();
    }

    /**
     * Create WordPress mime type column
     */
    public function wpMimeType(string $column = 'post_mime_type'): ColumnDefinition
    {
        return $this->string($column, 100)->default('');
    }

    /**
     * Create WordPress comment count column
     */
    public function wpCommentCount(string $column = 'comment_count'): ColumnDefinition
    {
        return $this->bigInteger($column)->default(0);
    }

    /**
     * Create WordPress post type column
     */
    public function wpPostType(string $column = 'post_type'): ColumnDefinition
    {
        return $this->string($column, 20)->default('post');
    }

    /**
     * Add WordPress timestamps (alias for wpDates)
     * Matches Laravel-style naming convention
     */
    public function wpTimestamps(string $prefix = 'post'): void
    {
        $this->wpDates($prefix);
    }

    /**
     * Add all standard WordPress post columns
     */
    public function wpPost(): void
    {
        $this->wpId();
        $this->wpAuthor();
        $this->wpDates();
        $this->wpContent();
        $this->wpTitle();
        $this->wpExcerpt();
        $this->wpStatus();
        $this->wpCommentStatus();
        $this->wpPingStatus();
        $this->wpPassword();
        $this->wpSlug();
        $this->text('to_ping');
        $this->text('pinged');
        $this->longText('post_content_filtered');
        $this->wpParent();
        $this->wpGuid();
        $this->wpMenuOrder();
        $this->wpPostType();
        $this->wpMimeType();
        $this->wpCommentCount();
    }

    /**
     * Add standard WordPress post indexes
     *
     * @codeCoverageIgnoreStart
     */
    public function wpPostIndexes(): void
    {
        $this->index(['post_type', 'post_status', 'post_date', 'ID']);
        $this->index('post_author');
        $this->index('post_name');
        $this->index('post_parent');
    }
    // @codeCoverageIgnoreEnd

    /**
     * Add WordPress user columns
     */
    public function wpUser(): void
    {
        $this->wpId();
        $this->string('user_login', 60)->default('')->index();
        $this->string('user_pass')->default('');
        $this->string('user_nicename', 50)->default('')->index();
        $this->string('user_email', 100)->default('')->index();
        $this->string('user_url', 100)->default('');
        $this->dateTime('user_registered')->default('0000-00-00 00:00:00');
        $this->string('user_activation_key')->default('');
        $this->integer('user_status')->default(0);
        $this->string('display_name', 250)->default('');
    }

    /**
     * Add WordPress meta table columns
     */
    public function wpMeta(string $objectType = 'post'): void
    {
        $this->bigIncrements('meta_id');
        $this->unsignedBigInteger($objectType.'_id')->default(0)->index();
        $this->string('meta_key')->nullable()->index();
        $this->longText('meta_value')->nullable();
    }

    /**
     * Add WordPress taxonomy columns
     */
    public function wpTaxonomy(): void
    {
        $this->bigIncrements('term_taxonomy_id');
        $this->unsignedBigInteger('term_id')->default(0);
        $this->string('taxonomy', 32)->default('')->index();
        $this->longText('description');
        $this->unsignedBigInteger('parent')->default(0);
        $this->bigInteger('count')->default(0);

        $this->unique(['term_id', 'taxonomy']);
    }

    /**
     * Add WordPress term columns
     */
    public function wpTerm(): void
    {
        $this->bigIncrements('term_id');
        $this->string('name', 200)->default('')->index();
        $this->string('slug', 200)->default('')->index();
        $this->bigInteger('term_group')->default(0);
    }

    /**
     * Add WordPress term relationship columns
     */
    public function wpTermRelationship(): void
    {
        $this->unsignedBigInteger('object_id')->default(0);
        $this->unsignedBigInteger('term_taxonomy_id')->default(0);
        $this->integer('term_order')->default(0);

        $this->primary(['object_id', 'term_taxonomy_id']);
        $this->index('term_taxonomy_id');
    }

    /**
     * Add WordPress option columns
     */
    public function wpOption(): void
    {
        $this->bigIncrements('option_id');
        $this->string('option_name', 191)->default('')->unique();
        $this->longText('option_value');
        $this->string('autoload', 20)->default('yes')->index();
    }

    /**
     * Add WordPress comment columns
     */
    public function wpComment(): void
    {
        $this->bigIncrements('comment_ID');
        $this->unsignedBigInteger('comment_post_ID')->default(0)->index();
        $this->text('comment_author');
        $this->string('comment_author_email', 100)->default('')->index();
        $this->string('comment_author_url', 200)->default('');
        $this->string('comment_author_IP', 100)->default('');
        $this->dateTime('comment_date')->default('0000-00-00 00:00:00');
        $this->dateTime('comment_date_gmt')->default('0000-00-00 00:00:00')->index();
        $this->text('comment_content');
        $this->integer('comment_karma')->default(0);
        $this->string('comment_approved', 20)->default('1');
        $this->string('comment_agent')->default('');
        $this->string('comment_type', 20)->default('comment');
        $this->unsignedBigInteger('comment_parent')->default(0)->index();
        $this->unsignedBigInteger('user_id')->default(0);

        $this->index(['comment_approved', 'comment_date_gmt']);
        $this->index('comment_post_ID');
        $this->index('comment_date_gmt');
        $this->index('comment_parent');
        $this->index('user_id');
    }

    /**
     * Create a foreign key reference to WordPress posts table
     */
    public function wpForeignPost(string $column = 'post_id', string $table = 'posts'): ColumnDefinition
    {
        $col = $this->unsignedBigInteger($column);

        $this->foreign($column)
            ->references('ID')
            ->on($table)
            ->onDelete('cascade');

        return $col;
    }

    /**
     * Create a foreign key reference to WordPress users table
     */
    public function wpForeignUser(string $column = 'user_id', string $table = 'users'): ColumnDefinition
    {
        $col = $this->unsignedBigInteger($column);

        $this->foreign($column)
            ->references('ID')
            ->on($table)
            ->onDelete('cascade');

        return $col;
    }

    /**
     * Create a foreign key reference to WordPress terms table
     */
    public function wpForeignTerm(string $column = 'term_id', string $table = 'terms'): ColumnDefinition
    {
        $col = $this->unsignedBigInteger($column);

        $this->foreign($column)
            ->references('term_id')
            ->on($table)
            ->onDelete('cascade');

        return $col;
    }

    /**
     * Add WooCommerce order columns
     */
    public function wcOrder(): void
    {
        $this->wpId('order_id');
        $this->string('order_key', 100)->unique();
        $this->unsignedBigInteger('customer_id')->default(0)->index();
        $this->string('billing_first_name', 100);
        $this->string('billing_last_name', 100);
        $this->string('billing_company', 100);
        $this->string('billing_address_1', 200);
        $this->string('billing_address_2', 200);
        $this->string('billing_city', 100);
        $this->string('billing_state', 100);
        $this->string('billing_postcode', 20);
        $this->string('billing_country', 20);
        $this->string('billing_email', 100)->index();
        $this->string('billing_phone', 100);
        $this->string('shipping_first_name', 100);
        $this->string('shipping_last_name', 100);
        $this->string('shipping_company', 100);
        $this->string('shipping_address_1', 200);
        $this->string('shipping_address_2', 200);
        $this->string('shipping_city', 100);
        $this->string('shipping_state', 100);
        $this->string('shipping_postcode', 20);
        $this->string('shipping_country', 20);
        $this->string('payment_method', 100);
        $this->string('payment_method_title', 100);
        $this->string('transaction_id', 100);
        $this->text('customer_ip_address');
        $this->text('customer_user_agent');
        $this->string('created_via', 100);
        $this->text('customer_note');
        $this->dateTime('date_created')->index();
        $this->dateTime('date_updated')->index();
        $this->dateTime('date_completed')->nullable()->index();
        $this->dateTime('date_paid')->nullable()->index();
        $this->string('cart_hash');
        $this->string('status', 20)->index();
        $this->string('currency', 10);
        $this->decimal('discount_total', 12, 2)->default(0);
        $this->decimal('discount_tax', 12, 2)->default(0);
        $this->decimal('shipping_total', 12, 2)->default(0);
        $this->decimal('shipping_tax', 12, 2)->default(0);
        $this->decimal('cart_tax', 12, 2)->default(0);
        $this->decimal('total', 12, 2)->default(0);
        $this->integer('version')->nullable();
        $this->boolean('prices_include_tax')->default(false);
    }

    /**
     * Add WooCommerce product columns
     *
     * @codeCoverageIgnoreStart
     */
    public function wcProduct(): void
    {
        $this->wpPost();
        // Products use the standard WordPress post structure
        // Additional product data is stored in postmeta
    }
    // @codeCoverageIgnoreEnd

    /**
     * Add WooCommerce order item columns
     */
    public function wcOrderItem(): void
    {
        $this->bigIncrements('order_item_id');
        $this->string('order_item_name');
        $this->string('order_item_type')->default('');
        $this->unsignedBigInteger('order_id');

        $this->index('order_id');
        $this->foreign('order_id')
            ->references('order_id')
            ->on('wc_orders')
            ->onDelete('cascade');
    }

    /**
     * Add WooCommerce High-Performance Order Storage (HPOS) structure
     */
    public function wcHposStructure(): void
    {
        // This would add all the HPOS-specific columns
        // HPOS is WooCommerce's new order storage system
        $this->wcOrder();

        // Add HPOS-specific indexes
        $this->index('status');
        $this->index('customer_id');
        $this->index('billing_email');
        $this->index('date_created');
        $this->index('date_updated');
    }
}

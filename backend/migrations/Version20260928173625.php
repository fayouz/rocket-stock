<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928173625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rocket Stock 0.1.0: places (site), catalogue, stores and offers, locations, levels, movements, equipment, shopping carts.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cart_line (id UUID NOT NULL, position INT NOT NULL, quantity DOUBLE PRECISION NOT NULL, pack_size DOUBLE PRECISION NOT NULL, pack_price DOUBLE PRECISION DEFAULT NULL, checked BOOLEAN NOT NULL, cart_id UUID NOT NULL, item_id UUID NOT NULL, location_id UUID NOT NULL, store_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3EF1B4CF1AD5CDBF ON cart_line (cart_id)');
        $this->addSql('CREATE INDEX IDX_3EF1B4CF126F525E ON cart_line (item_id)');
        $this->addSql('CREATE INDEX IDX_3EF1B4CF64D218E ON cart_line (location_id)');
        $this->addSql('CREATE INDEX IDX_3EF1B4CFB092A811 ON cart_line (store_id)');
        $this->addSql('CREATE TABLE equipment (id UUID NOT NULL, name VARCHAR(120) NOT NULL, place_id VARCHAR(36) DEFAULT NULL, room VARCHAR(80) DEFAULT NULL, serial VARCHAR(120) DEFAULT NULL, purchase_date DATE DEFAULT NULL, warranty_end DATE DEFAULT NULL, manual_document_ref VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, item_id UUID DEFAULT NULL, supplier_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D338D583126F525E ON equipment (item_id)');
        $this->addSql('CREATE INDEX IDX_D338D5832ADD6D8C ON equipment (supplier_id)');
        $this->addSql('CREATE TABLE item_offer (id UUID NOT NULL, preferred BOOLEAN NOT NULL, price DOUBLE PRECISION DEFAULT NULL, pack_size DOUBLE PRECISION NOT NULL, item_id UUID NOT NULL, supplier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_item_offer_item_supplier ON item_offer (item_id, supplier_id)');
        $this->addSql('CREATE INDEX IDX_3719CEE126F525E ON item_offer (item_id)');
        $this->addSql('CREATE INDEX IDX_3719CEE2ADD6D8C ON item_offer (supplier_id)');
        $this->addSql('CREATE TABLE location (id UUID NOT NULL, place_id VARCHAR(36) NOT NULL, name VARCHAR(80) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_location_place_name ON location (place_id, name)');
        $this->addSql('CREATE TABLE shopping_cart (id UUID NOT NULL, place_id VARCHAR(36) DEFAULT NULL, status VARCHAR(12) NOT NULL, completed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE site (id VARCHAR(36) NOT NULL, name VARCHAR(160) NOT NULL, source VARCHAR(8) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE stock_item (id UUID NOT NULL, name VARCHAR(120) NOT NULL, unit VARCHAR(24) NOT NULL, category VARCHAR(16) NOT NULL, sku VARCHAR(64) DEFAULT NULL, reorder_threshold DOUBLE PRECISION NOT NULL, reorder_qty INT NOT NULL, asin VARCHAR(32) DEFAULT NULL, subscription BOOLEAN NOT NULL, unit_cost DOUBLE PRECISION DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, supplier_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6017DDA2ADD6D8C ON stock_item (supplier_id)');
        $this->addSql('CREATE TABLE stock_level (id UUID NOT NULL, quantity DOUBLE PRECISION DEFAULT NULL, level VARCHAR(8) NOT NULL, threshold_override DOUBLE PRECISION DEFAULT NULL, target_quantity DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, location_id UUID NOT NULL, item_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_level_location_item ON stock_level (location_id, item_id)');
        $this->addSql('CREATE INDEX IDX_6FAD0E264D218E ON stock_level (location_id)');
        $this->addSql('CREATE INDEX IDX_6FAD0E2126F525E ON stock_level (item_id)');
        $this->addSql('CREATE TABLE stock_movement (id UUID NOT NULL, place_id VARCHAR(36) NOT NULL, type VARCHAR(12) NOT NULL, quantity DOUBLE PRECISION NOT NULL, reason VARCHAR(255) DEFAULT NULL, external_ref VARCHAR(120) DEFAULT NULL, origin VARCHAR(8) NOT NULL, origin_app VARCHAR(120) DEFAULT NULL, usage_kind VARCHAR(8) NOT NULL, unit_cost DOUBLE PRECISION DEFAULT NULL, occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, item_id UUID NOT NULL, location_id UUID NOT NULL, to_location_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BB1BC1B5B445906B ON stock_movement (external_ref)');
        $this->addSql('CREATE INDEX idx_stock_movement_place_at ON stock_movement (place_id, occurred_at)');
        $this->addSql('CREATE INDEX IDX_BB1BC1B5126F525E ON stock_movement (item_id)');
        $this->addSql('CREATE INDEX IDX_BB1BC1B564D218E ON stock_movement (location_id)');
        $this->addSql('CREATE INDEX IDX_BB1BC1B528DE1FED ON stock_movement (to_location_id)');
        $this->addSql('CREATE TABLE supplier (id UUID NOT NULL, name VARCHAR(120) NOT NULL, kind VARCHAR(12) NOT NULL, address VARCHAR(255) DEFAULT NULL, lat DOUBLE PRECISION DEFAULT NULL, lng DOUBLE PRECISION DEFAULT NULL, opening_hours VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE cart_line ADD CONSTRAINT FK_3EF1B4CF1AD5CDBF FOREIGN KEY (cart_id) REFERENCES shopping_cart (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cart_line ADD CONSTRAINT FK_3EF1B4CF126F525E FOREIGN KEY (item_id) REFERENCES stock_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cart_line ADD CONSTRAINT FK_3EF1B4CF64D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cart_line ADD CONSTRAINT FK_3EF1B4CFB092A811 FOREIGN KEY (store_id) REFERENCES supplier (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D583126F525E FOREIGN KEY (item_id) REFERENCES stock_item (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D5832ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE item_offer ADD CONSTRAINT FK_3719CEE126F525E FOREIGN KEY (item_id) REFERENCES stock_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE item_offer ADD CONSTRAINT FK_3719CEE2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_item ADD CONSTRAINT FK_6017DDA2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_level ADD CONSTRAINT FK_6FAD0E264D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_level ADD CONSTRAINT FK_6FAD0E2126F525E FOREIGN KEY (item_id) REFERENCES stock_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5126F525E FOREIGN KEY (item_id) REFERENCES stock_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B564D218E FOREIGN KEY (location_id) REFERENCES location (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B528DE1FED FOREIGN KEY (to_location_id) REFERENCES location (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_line DROP CONSTRAINT FK_3EF1B4CF1AD5CDBF');
        $this->addSql('ALTER TABLE cart_line DROP CONSTRAINT FK_3EF1B4CF126F525E');
        $this->addSql('ALTER TABLE cart_line DROP CONSTRAINT FK_3EF1B4CF64D218E');
        $this->addSql('ALTER TABLE cart_line DROP CONSTRAINT FK_3EF1B4CFB092A811');
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT FK_D338D583126F525E');
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT FK_D338D5832ADD6D8C');
        $this->addSql('ALTER TABLE item_offer DROP CONSTRAINT FK_3719CEE126F525E');
        $this->addSql('ALTER TABLE item_offer DROP CONSTRAINT FK_3719CEE2ADD6D8C');
        $this->addSql('ALTER TABLE stock_item DROP CONSTRAINT FK_6017DDA2ADD6D8C');
        $this->addSql('ALTER TABLE stock_level DROP CONSTRAINT FK_6FAD0E264D218E');
        $this->addSql('ALTER TABLE stock_level DROP CONSTRAINT FK_6FAD0E2126F525E');
        $this->addSql('ALTER TABLE stock_movement DROP CONSTRAINT FK_BB1BC1B5126F525E');
        $this->addSql('ALTER TABLE stock_movement DROP CONSTRAINT FK_BB1BC1B564D218E');
        $this->addSql('ALTER TABLE stock_movement DROP CONSTRAINT FK_BB1BC1B528DE1FED');
        $this->addSql('DROP TABLE cart_line');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('DROP TABLE item_offer');
        $this->addSql('DROP TABLE location');
        $this->addSql('DROP TABLE shopping_cart');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE stock_item');
        $this->addSql('DROP TABLE stock_level');
        $this->addSql('DROP TABLE stock_movement');
        $this->addSql('DROP TABLE supplier');
    }
}

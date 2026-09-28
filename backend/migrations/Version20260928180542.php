<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928180542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rocket Stock 0.2.0: EAN, store search links, Amazon carts (ASIN), supplier order e-mail, purchase orders.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE purchase_order (id UUID NOT NULL, recipient VARCHAR(180) NOT NULL, sent_by VARCHAR(180) NOT NULL, sent_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, message_id VARCHAR(64) DEFAULT NULL, status VARCHAR(16) NOT NULL, line_count INT NOT NULL, cart_id UUID NOT NULL, supplier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_purchase_order_cart_supplier ON purchase_order (cart_id, supplier_id)');
        $this->addSql('CREATE INDEX IDX_21E210B21AD5CDBF ON purchase_order (cart_id)');
        $this->addSql('CREATE INDEX IDX_21E210B22ADD6D8C ON purchase_order (supplier_id)');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B21AD5CDBF FOREIGN KEY (cart_id) REFERENCES shopping_cart (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT FK_21E210B22ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cart_line ADD asin VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE cart_line ADD product_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_offer ADD asin VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_offer ADD product_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_item ADD ean VARCHAR(13) DEFAULT NULL');
        $this->addSql('ALTER TABLE supplier ADD order_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE supplier ADD search_url_template VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE supplier ADD amazon BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE supplier ADD amazon_domain VARCHAR(40) DEFAULT \'amazon.fr\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase_order DROP CONSTRAINT FK_21E210B21AD5CDBF');
        $this->addSql('ALTER TABLE purchase_order DROP CONSTRAINT FK_21E210B22ADD6D8C');
        $this->addSql('DROP TABLE purchase_order');
        $this->addSql('ALTER TABLE cart_line DROP asin');
        $this->addSql('ALTER TABLE cart_line DROP product_url');
        $this->addSql('ALTER TABLE item_offer DROP asin');
        $this->addSql('ALTER TABLE item_offer DROP product_url');
        $this->addSql('ALTER TABLE stock_item DROP ean');
        $this->addSql('ALTER TABLE supplier DROP order_email');
        $this->addSql('ALTER TABLE supplier DROP search_url_template');
        $this->addSql('ALTER TABLE supplier DROP amazon');
        $this->addSql('ALTER TABLE supplier DROP amazon_domain');
    }
}

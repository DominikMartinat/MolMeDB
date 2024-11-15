<?php 

/**
 * Adding columns with accuracy to the transporter interaction table
 * Version 1.04 release
 * 
 * @author Jakub Juračka <jakub.juracka55@gmail.com>
 */
$upgrade_sql = array
(
   "ALTER TABLE `transporters` CHANGE `type` `type` INT(150) NULL DEFAULT NULL;",
   'ALTER TABLE `substances` ADD `inchikey` TEXT NULL DEFAULT NULL AFTER `SMILES`;',
   'ALTER TABLE `interaction` ADD `comment` TEXT NULL DEFAULT NULL AFTER `id_reference`;',
   'ALTER TABLE `interaction` DROP INDEX `UNIQUE_KEY_COMB`;',
   "ALTER TABLE `interaction` ADD `validated` INT(2) NOT NULL DEFAULT '0' AFTER `user_id`;"
);

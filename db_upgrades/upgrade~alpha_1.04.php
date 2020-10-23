<?php 

/**
 * Adding columns with accuracy to the transporter interaction table
 */

/**
 * Version 1.03 release
 * 
 * @author Jakub Juračka <jakub.juracka55@gmail.com>
 */
$upgrade_sql = array
(
   "ALTER TABLE `transporters` ADD `Km_acc` FLOAT UNSIGNED NULL DEFAULT NULL AFTER `Km`;",
   "ALTER TABLE `transporters` ADD `EC50_acc` FLOAT UNSIGNED NULL DEFAULT NULL AFTER `EC50`;",
   "ALTER TABLE `transporters` ADD `Ki_acc` FLOAT UNSIGNED NULL DEFAULT NULL AFTER `Ki`;",
   "ALTER TABLE `transporters` ADD `IC50_acc` FLOAT UNSIGNED NULL DEFAULT NULL AFTER `IC50`;",
   "ALTER TABLE `transporters` CHANGE `type` `type` INT(150) NULL DEFAULT NULL;"
);

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';

CREATE SCHEMA IF NOT EXISTS `dbhtest` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci ;
USE `dbhtest`;

-- -----------------------------------------------------
-- Table `dbhtest`.`soldiers`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `dbhtest`.`soldiers` (
  `soldierId` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `name` VARCHAR(255) NOT NULL ,
  `rank` VARCHAR(32) NOT NULL ,
  `division` VARCHAR(32) NOT NULL ,
  `power` INT UNSIGNED NOT NULL ,
  `health` INT UNSIGNED NOT NULL ,
  PRIMARY KEY (`soldierId`) )
ENGINE = InnoDB;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

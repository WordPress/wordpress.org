-- Database for the module tests, which recreate their schema on every run.
CREATE DATABASE IF NOT EXISTS `freescout-test`;
GRANT ALL ON `freescout-test`.* TO 'freescout'@'%';

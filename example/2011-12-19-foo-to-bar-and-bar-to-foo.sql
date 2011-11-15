
ALTER TABLE `foo`
ADD `bar` char(3) NOT NULL DEFAULT '' AFTER `foo`;

ALTER TABLE `bar`
ADD `foo` char(3) NOT NULL DEFAULT '' AFTER `bar`;

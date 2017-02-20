INSERT INTO `ospos_items` (`name`, `category`, `supplier_id`, `item_number`, `description`, `cost_price`, `unit_price`, `reorder_level`, `receiving_quantity`, `item_id`, `pic_id`, `allow_alt_description`, `is_serialized`, `deleted`) VALUES
('手机', '电子', NULL, NULL, '', '1000.00', '1200.00', '0.000', '0.000', 1, NULL, 0, 0, 0),
('电脑', '电子', NULL, NULL, '', '3000.00', '4000.00', '0.000', '0.000', 2, NULL, 0, 0, 0);

INSERT INTO `ospos_item_quantities` (`item_id`, `location_id`, `quantity`) VALUES
(1, 1, '100.000'),
(2, 1, '200.000'),
(1, 2, '400.000'),
(2, 2, '100.000');

INSERT INTO `ospos_inventory` (`trans_id`, `trans_items`, `trans_user`, `trans_date`, `trans_comment`, `trans_location`, `trans_inventory`) VALUES
(1, 1, 1, '2017-02-04 19:27:56', 'Manual Edit of Quantity', 1, '100.000'),
(2, 2, 1, '2017-02-01 10:18:31', 'Manual Edit of Quantity', 1, '200.000'),
(3, 1, 1, '2017-01-12 16:17:56', 'Manual Edit of Quantity', 2, '400.000'),
(4, 2, 1, '2017-05-24 15:48:31', 'Manual Edit of Quantity', 2, '100.000');

INSERT INTO `ospos_people` (`first_name`, `last_name`, `gender`, `phone_number`, `email`, `address_1`, `address_2`, `city`, `state`, `zip`, `country`, `comments`, `person_id`) VALUES
('张三', '', 1, '555-555-555', '', '北京市朝阳区', '', '', '', '', '', '', 4),
('李四', '', 1, '1328****878', '', '浙江省杭州市', '', '', '', '', '', '', 5),
('小张', '', 1, '1809****765', '', '广州', '', '', '', '', '', '', 6);

INSERT INTO `ospos_suppliers` (`person_id`, `company_name`, `agency_name`, `account_number`, `deleted`) VALUES
(4, '一个科技公司', '', NULL, 0);

INSERT INTO `ospos_customers` (`person_id`, `company_name`, `account_number`, `taxable`, `discount_percent`, `deleted`) VALUES
(5, 'O2O公司', NULL, 0, '0.00', 0);

INSERT INTO `ospos_staffs` (`person_id`, `account_number`, `deleted`) VALUES (6,NULL, 0);

UPDATE `ospos_stock_locations` SET `location_name`='仓库一' WHERE `location_id`=1;

INSERT INTO `ospos_item_kits` (`item_kit_id`, `name`, `description`) VALUES (1, '礼包', '');
INSERT INTO `ospos_item_kit_items` (`item_kit_id`, `item_id`, `quantity`) VALUES (1, 1, '3.000'),(1, 2, '1.000');

UPDATE `ospos_app_config` SET `value`='电器公司' WHERE `key`='company';
INSERT INTO `ospos_stock_locations` ( `deleted`, `location_name` ) VALUES ('0', '仓库二');
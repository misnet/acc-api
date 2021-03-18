/*
 Navicat Premium Data Transfer

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 50724
 Source Host           : localhost:3306
 Source Schema         : dpj_acc

 Target Server Type    : MySQL
 Target Server Version : 50724
 File Encoding         : 65001

 Date: 05/05/2019 22:50:46
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for t_apps
-- ----------------------------
DROP TABLE IF EXISTS `t_apps`;
CREATE TABLE `t_apps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_bin NOT NULL COMMENT 'APP名称',
  `secret` varchar(100) COLLATE utf8mb4_bin NOT NULL COMMENT 'AppSecret',
  `disabled` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否禁用',
  `short_desc` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL COMMENT '描述',
  `acc_resources_xml` text COLLATE utf8mb4_bin COMMENT '权限资源xml内容',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='应用';

-- ----------------------------
-- Table structure for t_menu
-- ----------------------------
DROP TABLE IF EXISTS `t_menu`;
CREATE TABLE `t_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT '' COMMENT 'url地址',
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `sort_by_weight` int(11) NOT NULL DEFAULT '0' COMMENT '显示顺序',
  `display` enum('1','0') NOT NULL DEFAULT '1',
  `app_id` int(11) DEFAULT NULL COMMENT '应用ID',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for t_regions
-- ----------------------------
DROP TABLE IF EXISTS `t_regions`;
CREATE TABLE `t_regions` (
  `id` int(10) unsigned NOT NULL COMMENT 'ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '地区名',
  `parent_id` int(10) NOT NULL COMMENT '父ID',
  `zipcode` varchar(10) DEFAULT NULL COMMENT '邮编',
  `sort_index` tinyint(3) unsigned DEFAULT '50' COMMENT '排序',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `pid` (`parent_id`,`sort_index`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for t_role
-- ----------------------------
DROP TABLE IF EXISTS `t_role`;
CREATE TABLE `t_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(40) NOT NULL COMMENT '角色名称',
  `role_type` tinyint(1) NOT NULL DEFAULT '3' COMMENT '角色类型：1管理员,2基础角色',
  `assign_policy` tinyint(1) NOT NULL DEFAULT '0' COMMENT '自动分配：0不自动,1自动给登陆会员,2自动给未登陆会员',
  `priority` smallint(6) NOT NULL DEFAULT '0',
  `default_allow` tinyint(4) DEFAULT '0' COMMENT '默认权限',
  `app_id` int(11) DEFAULT NULL COMMENT '应用ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `priority` (`priority`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='角色表';

-- ----------------------------
-- Table structure for t_role_menu
-- ----------------------------
DROP TABLE IF EXISTS `t_role_menu`;
CREATE TABLE `t_role_menu` (
  `rid` int(11) NOT NULL COMMENT '角色id',
  `mid` int(11) NOT NULL COMMENT '菜单id',
  PRIMARY KEY (`rid`,`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='角色菜单分配表';

-- ----------------------------
-- Table structure for t_role_res
-- ----------------------------
DROP TABLE IF EXISTS `t_role_res`;
CREATE TABLE `t_role_res` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rid` int(11) NOT NULL COMMENT '角色id',
  `rescode` varchar(50) NOT NULL COMMENT '资源code',
  `opcode` varchar(50) DEFAULT NULL COMMENT '操作code',
  `is_allow` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否允许',
  `app_id` int(11) NOT NULL COMMENT 'APPID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `rid` (`rid`,`rescode`,`opcode`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8 COMMENT='角色资源分配表';

-- ----------------------------
-- Table structure for t_role_user
-- ----------------------------
DROP TABLE IF EXISTS `t_role_user`;
CREATE TABLE `t_role_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `rid` (`rid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COMMENT='角色用户分配表';

-- ----------------------------
-- Table structure for t_sendmsg_logs
-- ----------------------------
DROP TABLE IF EXISTS `t_sendmsg_logs`;
CREATE TABLE `t_sendmsg_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `msg_to` varchar(255) NOT NULL DEFAULT '' COMMENT '消息接收者',
  `msg_body` text COMMENT '消息内容',
  `msg_id` varchar(64) DEFAULT '' COMMENT '消息id',
  `msg_sender` varchar(40) DEFAULT '' COMMENT '消息发送者',
  `error_info` varchar(255) DEFAULT '' COMMENT '结果提示信息',
  `send_state` int(11) DEFAULT '1' COMMENT '发送结果状态值',
  `send_time` int(11) DEFAULT '0' COMMENT '发送时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='发送的消息日志';

-- ----------------------------
-- Table structure for t_sysparams
-- ----------------------------
DROP TABLE IF EXISTS `t_sysparams`;
CREATE TABLE `t_sysparams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '参数名称',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '参数描述',
  `keyname` varchar(100) NOT NULL DEFAULT '' COMMENT 'Key名',
  `value_type` tinyint(4) DEFAULT '1' COMMENT '1字符串2布尔值3日期4数字',
  `current_value` varchar(255) NOT NULL DEFAULT '' COMMENT '当前值',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='系统配置';

-- ----------------------------
-- Table structure for t_user
-- ----------------------------
DROP TABLE IF EXISTS `t_user`;
CREATE TABLE `t_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT '' COMMENT '用户名',
  `password` varchar(70) NOT NULL DEFAULT '' COMMENT '密码',
  `mobile` varchar(15) NOT NULL DEFAULT '' COMMENT '手机号',
  `email` varchar(50) NOT NULL DEFAULT '' COMMENT 'EMAIL',
  `create_time` int(11) DEFAULT '0' COMMENT '注册时间',
  `last_visit_ip` varchar(15) NOT NULL DEFAULT '' COMMENT '最近一次访问IP',
  `last_visit_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近一次访问时间',
  `gender` tinyint(4) NOT NULL DEFAULT '1' COMMENT '性别',
  `fullname` varchar(50) DEFAULT '' COMMENT '姓名',
  `memo` varchar(255) default '' comment '备注',
  `mobile_verified` enum('0','1') NOT NULL DEFAULT '0' COMMENT '手机是否认证',
  `email_verified` enum('0','1') DEFAULT '0' COMMENT '邮箱是否认证',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COMMENT='用户表';

-- ----------------------------
-- Table structure for t_user_bind_app
-- ----------------------------
DROP TABLE IF EXISTS `t_user_bind_app`;
CREATE TABLE `t_user_bind_app` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `app_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`,`app_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='用户适用APPS';

alter table `t_user` add fullname varchar(50) null comment '姓名';
alter table `t_user` drop column realname;
SET FOREIGN_KEY_CHECKS = 1;

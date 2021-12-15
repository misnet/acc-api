alter table `t_user` drop column realname;
alter table `t_user` add column fullname varchar(50) null default '' comment '姓名';
alter table `t_user` add column memo varchar(255) null default '' comment '备注';
alter table `t_apps` add column allow_auto_create_user tinyint not null default 0 comment '是否允许自动创建用户';
alter table `t_oauth` change oauth_id varchar(128) not null default '' comment '第三方用户ID';
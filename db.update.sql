alter table `t_user` drop column realname;
alter table `t_user` add column fullname varchar(50) null default '' comment '姓名';
alter table `t_user` add column memo varchar(255) null default '' comment '备注';
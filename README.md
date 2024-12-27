## ACC-API
- ACC是Application Controller Center的简写，基于Phalcon框架开发的用户权限管理系统，提供了应用、用户、角色、权限、菜单、日志等功能。
- 应用管理：可以添加多个应用，每个应用可以有不同的用户、角色、菜单以及配置项
- 用户管理：用户信息包括用户名、手机、邮箱、姓名等信息，可以对用户分配角色
- 角色管理：角色信息包括角色名、默认权限、分配策略等信息，可以对角色分配菜单、资源以及指定用户
- 菜单管理：菜单信息包括菜单名、菜单URL、是否显示等信息
- 配置管理：配置信息包括配置名、配置KEY、配置值、配置描述等信息

## 说明
- 本系统配合kuga-server-v3 可搭建出完整API服务
- 前端配合acc-web 2.0 https://github.com/misnet/acc-web
- 需要Php7.4以上版本，需要yaml、gettext、gd、pdo_mysql、redis、phalcon扩展
- phalcon需要v5以上版本
- 需要mysql5.7或mysql8以及redis支持
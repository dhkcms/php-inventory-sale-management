#php-inventory-sale-management

这个项目基于jekkos的开源项目[opensourcepos](https://github.com/jekkos/opensourcepos)，版本号是3.0.2

目前实现了如下一些功能：
- 物品管理（将所有的商品、原材料以及虚拟物品放在一起管理）
- 物品包管理（可以将一些相关的物品放进物品包，主要用于产品加工时的原材料的管理）
- 物品销售、采购、加工制造
- 多仓库管理，可以自由切换到各个仓库查看，也可以对各个仓库间的调货进行管理
- 人员管理，包括对顾客、供应商、员工的管理

技术方面，这个系统使用php+mysql，后端框架为CodeIgniter 3.x，前端使用Bootstrap 3.x，运行环境要求如下：
- php>=5.5（暂不支持php7.x），必需的php模块为php-gd, php-bcmath, php-intl, php-sockets, php-mcrypt 和 php-curl
- mysql 5.5,5.6 和 5.7
- Apache 2.2 和 2.4

登陆 用户名：admin，密码：pointofsale


如果代码无法运行，可以查看apache的error.log，并参考[这里](https://github.com/jekkos/opensourcepos#faq)的FAQ，通常都是对应的php模块有问题（在windows下php-intl 这个模块需要特别注意，可以参考[这里](http://stackoverflow.com/questions/1451468/intl-extension-installing-php-intl-dll)）

数据库文件database/tables.sql为所有表的结构信息，database/data.sql为示例数据

> 相对于原始项目[opensourcepos](https://github.com/jekkos/opensourcepos)，我们更改的原则是不统计用户所有订单的收支情况，而是致力于完整地保存每个订单以及生产的最真实和具体的情况。

网页截图![Alt text](./screenshot.png?raw=true)

代码使用MIT协议，但应原始项目的要求，每个页面下方关于[opensourcepos](https://github.com/jekkos/opensourcepos)的描述不能删除和修改。具体可以查看LICENSE文件。
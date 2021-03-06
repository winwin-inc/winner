# 开发工具

## 安装

```bash
curl -sS http://mirrors.winwin.group/installer.php | php -- --install-bin=$HOME/bin winner
```

## lint 命令

lint 命令用于检查指定目录下文件语法错误。

```bash
winner.phar lint [-j jobs] <directory>
```
## enum-helper 命令

enum-helper 用于生成 Enum 文件 PhpStorm 方法注解。

```bash
winner.phar enum [-i] <file>
```

批量处理目录下所有文件

```bash
grep -lR 'extends Enum' src | xargs -l winner.phar enum -i
```
Mac下的grep语法和xargs语法跟Linux不一样   
**Mac OSX 批量处理目录下所有文件**
```shell
grep -Hr 'extends Enum' src | awk -F ':' {'print $1'} |  xargs -I {} winner.phar enum -i {}
```
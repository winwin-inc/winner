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
grep -R 'extends Enum' src | xargs -l winner.phar enum -i
```

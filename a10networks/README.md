# Scripts for A10 Loadbalancer Monitoring

### a10_vrrp.php - 

  - will check if an active/passive system is ok

Example:
```
php a10_vrrp.php -m active.example -s slave.example.com -u admin -p 'PASSWORD' -v 0
OK - Unit 1 and 2 are Active/Standby for VRRID 0
```

Icinga2 Command Specification:
```
object CheckCommand "check_a10_vrrp" {
    import "plugin-check-command"
    command = [ PluginDir + "/a10_vrrp.php" ]
        arguments = {
        "-m" = "$master$"
        "-s" = "$slave$"
        "-u" = "$user$"
        "-p" = "$pass$"
        "-v" = "$vrrid$"
        }
        vars.user  = "admin"
        vars.pass  = "probablydefaultpw"
        vars.vrrid = "0"
}

```

Icinga2 Example Definition:
```
object Service "N15 - A10 Loadbalancer lb01/lb02" {
    import "satellite-service"
    check_command               = "check_a10_vrrp"
        host_name               = "icingamaster.example.com"
        vars.master             = "lb01.example.com"
        vars.slave              = "lb02.example.com"
}

```

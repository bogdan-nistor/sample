ckage main

import (
	"fmt"
	"gmon/glog"
	"gmon/watch"
	"gmon/watch/config"
	"gmon/watch/rest"
	"strconv"
	"time"
)

var l = glog.GetLogger("main")

func main() {
	conf := c.GetConfig()
	fmt.Println(conf)
	watcher := startPolling(conf)
	router := rest.GetRouter(watcher, &conf)
	router.Run(":" + strconv.Itoa(conf.Port))
}

// StartPolling starts the polling loop
func startPolling(conf c.Config) *watch.WatchingContainer {

	// TODO: add a lock and test it
	// TODO: add SIG handling: SIGTERM, SIGHUP
	w := watch.NewContainer(conf.ChangeTesholdPerc)
	go func() {
		for {
			w.Refresh()
			time.Sleep(time.Duration(conf.ScanIntervalSec))
		}
	}()
	return w
}


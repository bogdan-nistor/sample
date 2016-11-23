ckage process

import (
	"fmt"
	conv "gmon/conversions"
	"gmon/glog"
	"gmon/ps"
	"math"
	"time"
)

// WatchedProcess contains the process metrics and the PID of the process
type WatchedProcess struct {
	Cmd                 string
	CPU                 float64
	Pid                 int
	Ppid                int
	Mem                 float64
	VirtualSizeMb       float64
	VirtualSizePercent  float64
	ResidentSizeMb      float64
	ResidentSizePercent float64 // TODO: how shall I implement this?
	UID                 int
	Utime               int
	State               string // enum: R is running, S is sleeping, D is sleeping in an uninterruptible wait, Z is zombie, T is traced or stopped
	Checked             int
	Children            []int
}

var l = glog.GetLogger("watch")

// NewWatchedProcess returns an initiated struct  -reading the PS data from the PS table
// If PID == 0 the data refers to the host.
func NewWatchedProcess(pid int, ppid int) *WatchedProcess {

	var newProcess WatchedProcess
	if pid < 0 {
		return &newProcess
	}

	psRaw, err := ps.GetProcessTable(pid)
	if err != nil {
		panic(err)
	}
	return castPsRow2Process(psRaw)
}

// Update returns the updated pointer using the int as treshold for changes.
func (old *WatchedProcess) Update(new *WatchedProcess, tr float64) *WatchedProcess {
	if overTreshold(old.Mem, new.Mem, tr) {
		return new
	}
	if overTreshold(old.ResidentSizeMb, new.ResidentSizeMb, tr) {
		return new
	}
	return old
}

func overTreshold(x, y, tr float64) bool {
	diff := x / math.Abs(x-y)
	if diff > tr {
		return true
	}
	return false
}

func castPsRow2Process(psRaw []byte) *WatchedProcess {

	var p WatchedProcess
	// var psString = "pid,cmd,ppid,vsize,rss,pcpu,size,uid,utime,state"
	var pid, ppid, vsize, rss, size, uid int
	var pcpu float64
	var cmd, utime, state string
	c, err := fmt.Sscan(string(psRaw), &pid, &cmd, &ppid, &vsize, &rss, &pcpu, &size, &uid, &utime, &state)
	//l.Debug("Scanned", c, "element of the 10")
	//l.Debug(string(psRaw))
	if err != nil {
		panic(err)
	}

	if c != 10 {
		l.Warning("Less or more elements got from psRaw:", c)
	}

	// TODO: I need the system mem size from 0
	p.Cmd = cmd
	p.CPU = pcpu
	p.Pid = pid
	p.Ppid = ppid
	//p.Mem =  // TODO calculate
	p.VirtualSizeMb = conv.BytesToMb(float64(vsize))
	// p.VirtualSizePercent =
	p.ResidentSizeMb = conv.BytesToMb(float64(rss))
	// p.ResidentSizePercent // TODO calculate
	p.UID = uid

	if uT, err := time.Parse("00:00:00", utime); err == nil {
		p.Utime = int(uT.Unix())
	} else {
		p.Utime = -1
	}

	p.State = state
	// Checked = int // TODO: not implemented
	// Children =             []int // TODO: not implemented
	return &p
}

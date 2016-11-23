ckage watch

import (
	"fmt"
	"gmon/glog"
	"gmon/ps"
	"gmon/watch/process"
	"os"
	"strings"
)

var l = glog.GetLogger("watch")

// WatchingContainer contains a read channel to receive instrucions
// and a map of id of processes to scan.
// TODO: add a watch for the system itself
type WatchingContainer struct {
	Processes map[int]*process.WatchedProcess
	treshold  float64
	// system    System // TODO: add system
}

// Dummy is an empty watched process with -1 pid and no values.
var Dummy = process.NewWatchedProcess(-1, 0)

// NewContainer return a new *WatchingContainer
func NewContainer(tr float64) *WatchingContainer {
	processes := make(map[int]*process.WatchedProcess)
	watch := &WatchingContainer{processes, float64(tr)}
	watch.Add(os.Getpid(), 0)
	return watch
}

// Add registers a process in the WatchingContainer returning the new process
func (w *WatchingContainer) Add(p, ppid int) *process.WatchedProcess {
	l.Debug("Adding process pid", p, "to the process list")
	if _, ok := w.Processes[p]; ok == false {
		l.Debug("-- Process", p, "pid not found in table, creating new processwatcher")
		np := process.NewWatchedProcess(p, ppid)
		w.Processes[p] = np
	}
	np, _ := w.Processes[p]
	return np
}

// Delete removes a process from the watchlist
func (w *WatchingContainer) Delete(p int) {
	if _, ok := w.Processes[p]; ok == true {
		delete(w.Processes, p)
	}
}

// Get returns the ([]struct, ok) for a watched process and true if the process exists
// a dummy value and false if not. If pid is <0 all the processes are returned.
func (w *WatchingContainer) Get(p int) ([]*process.WatchedProcess, bool) {

	fmt.Printf("Requested: %d", p)
	if p < 0 {
		ret := make([]*process.WatchedProcess, len(w.Processes))
		p := 0
		for _, v := range w.Processes {
			ret[p] = v
			p++
		}
		return ret, true
	}
	ret := make([]*process.WatchedProcess, 1)
	if pr, ok := w.Processes[p]; ok == true {
		ret[0] = pr
		return ret, true
	}
	ret[0] = Dummy
	return ret, false
}

// Refresh re-reads the ps table and refreshes the process data
func (w *WatchingContainer) Refresh() error {

	psTable, err := ps.GetProcessTable(0)
	if err != nil {
		return err
	}

	for _, r := range strings.Split(string(psTable), "\n") {
		rowPid, err := ps.GetPsPID([]byte(r))

		if err != nil {
			return err
		}

		if proc, ok := w.Processes[rowPid]; ok == true {

			newStatus := process.NewWatchedProcess(proc.Pid, proc.Ppid)
			w.Processes[proc.Pid] = proc.Update(newStatus, w.treshold)
		}
	}
	return nil
}


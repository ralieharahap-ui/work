import { useMemo, useState } from 'react';
import { STATUSES, STATUS_DOT } from './constants';
import TaskCard from './TaskCard';

export default function KanbanView({ tasks, onEdit, onStatusChange, onCloseRequest, canEdit }) {
    const [filterCat, setFilterCat] = useState('All');

    const filteredTasks = useMemo(() => {
        let result = tasks;
        if (filterCat !== 'All') result = result.filter((t) => t.category === filterCat);
        const prioScore = { Urgent: 4, High: 3, Medium: 2, Low: 1 };
        return [...result].sort((a, b) => prioScore[b.priority] - prioScore[a.priority]);
    }, [tasks, filterCat]);

    return (
        <div className="h-full flex flex-col">
            <div className="mb-4 flex gap-2">
                <select
                    value={filterCat}
                    onChange={(e) => setFilterCat(e.target.value)}
                    className="bg-white border border-black/10 rounded-md px-3 py-1.5 text-sm outline-none shadow-sm text-[rgba(0,0,0,0.9)]"
                >
                    <option value="All">Semua Kategori</option>
                    <option value="Operasional">Hanya Operasional</option>
                    <option value="Strategis">Hanya Strategis</option>
                </select>
            </div>

            <div className="flex-1 flex gap-4 overflow-x-auto pb-4">
                {STATUSES.map((status) => {
                    const colTasks = filteredTasks.filter((t) => t.status === status);
                    return (
                        <div key={status} className="bg-warm-white rounded-xl p-3 min-w-[280px] w-72 flex flex-col shrink-0 border border-black/10">
                            <div className="flex justify-between items-center mb-3 px-1">
                                <h3 className="font-semibold text-[rgba(0,0,0,0.8)] text-sm flex items-center gap-2">
                                    <span className={`w-2.5 h-2.5 rounded-full ${STATUS_DOT[status]}`} />
                                    {status}
                                </h3>
                                <span className="bg-white border border-black/10 text-warm-500 text-xs py-0.5 px-2 rounded-full font-medium">
                                    {colTasks.length}
                                </span>
                            </div>

                            <div className="flex-1 overflow-y-auto space-y-3 pr-1 max-h-[65vh]">
                                {colTasks.map((task) => (
                                    <TaskCard
                                        key={task.id}
                                        task={task}
                                        canEdit={canEdit}
                                        onEdit={() => onEdit(task)}
                                        onStatusChange={(newStatus) => onStatusChange(task, newStatus)}
                                        onCloseRequest={() => onCloseRequest(task)}
                                    />
                                ))}
                                {colTasks.length === 0 && (
                                    <div className="h-20 border-2 border-dashed border-black/10 rounded-lg flex items-center justify-center text-xs text-warm-300">
                                        Tidak ada task
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

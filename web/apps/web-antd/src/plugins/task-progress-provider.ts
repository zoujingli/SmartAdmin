export interface TaskProgressStatus {
  logs?: string[];
  progress?: {
    current?: number;
    message?: string;
    percent?: number;
    total?: number;
    updated_at?: string;
  };
  stat?: string;
}

type TaskProgressProvider = (taskId: string, limit?: number) => Promise<TaskProgressStatus>;

let taskProgressProvider: null | TaskProgressProvider = null;

export function configureTaskProgressProvider(provider: TaskProgressProvider) {
  taskProgressProvider = provider;
}

export function getTaskProgressProvider() {
  return taskProgressProvider;
}

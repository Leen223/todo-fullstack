
const taskForm = document.getElementById("task-form");
const taskInput = document.getElementById("task-input");
const statusEl = document.getElementById("status");
const errorEl = document.getElementById("error");
const listEl = document.getElementById("todo-list");
const emptyEl = document.getElementById("empty-message");

function showError(message) {
    errorEl.textContent = message;
    errorEl.hidden = !message;
}

function updateEmptyMessage() {
    emptyEl.hidden = listEl.children.length > 0;
}

// Runs an API call with a loading message and a friendly error.
// Returns { ok: true, value } or { ok: false }.
async function run(action, loadingText) {
    showError("");
    statusEl.textContent = loadingText;
    statusEl.hidden = false;
    document.body.classList.add("busy");
    try {
        return { ok: true, value: await action() };
    } catch (err) {
        showError(err.message);
        return { ok: false };
    } finally {
        statusEl.hidden = true;
        document.body.classList.remove("busy");
    }
}

function makeButton(text, className) {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = text;
    if (className) button.className = className;
    return button;
}

function createTaskItem(task, isEditing = false) {
    const li = document.createElement("li");
    const buttons = document.createElement("div");
    buttons.className = "task-buttons";

    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.checked = task.is_completed;
    checkbox.disabled = isEditing;
    checkbox.setAttribute("aria-label", "Mark task as completed");


    if (isEditing) {
        const input = document.createElement("input");
        input.type = "text";
        input.value = task.title;
        input.maxLength = 255;
        input.setAttribute("aria-label", "Task title");

        const saveBtn = makeButton("Save");
        const cancelBtn = makeButton("Cancel");

        const saveEdit = async () => {
            const newTitle = input.value.trim();
            if (!newTitle) {
                showError("Task title cannot be empty.");
                return;
            }
            const res = await run(() => Api.updateTask(task.id, { title: newTitle }), "Saving…");
            if (res.ok) li.replaceWith(createTaskItem(res.value));
        };

        saveBtn.addEventListener("click", saveEdit);
        cancelBtn.addEventListener("click", () => li.replaceWith(createTaskItem(task)));
        input.addEventListener("keydown", (event) => {
            if (event.key === "Enter") saveEdit();
            if (event.key === "Escape") cancelBtn.click();
        });

        buttons.append(saveBtn, cancelBtn);
        li.append(checkbox, input, buttons);
        setTimeout(() => input.focus(), 0);
        return li;
    }

   
    const title = document.createElement("span");
    title.textContent = task.title;
    title.classList.toggle("completed", task.is_completed);

    checkbox.addEventListener("change", async () => {
        const wanted = checkbox.checked;
        const res = await run(() => Api.updateTask(task.id, { is_completed: wanted }), "Saving…");
        if (res.ok) {
            li.replaceWith(createTaskItem(res.value));
        } else {
            checkbox.checked = !wanted;
        }
    });

    const editBtn = makeButton("Edit");
    editBtn.addEventListener("click", () => li.replaceWith(createTaskItem(task, true)));

    const deleteBtn = makeButton("Delete", "delete-btn");
    deleteBtn.addEventListener("click", async () => {
        const res = await run(() => Api.deleteTask(task.id), "Deleting…");
        if (res.ok) {
            li.remove();
            updateEmptyMessage();
        }
    });

    buttons.append(editBtn, deleteBtn);
    li.append(checkbox, title, buttons);
    return li;
}

taskForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const title = taskInput.value.trim();
    if (!title) {
        showError("Please enter a task title.");
        taskInput.focus();
        return;
    }

    const res = await run(() => Api.createTask(title), "Adding task…");
    if (res.ok) {
        listEl.prepend(createTaskItem(res.value));
        updateEmptyMessage();
        taskInput.value = "";
        taskInput.focus();
    }
});

async function loadTasks() {
    const res = await run(() => Api.getTasks(), "Loading tasks…");
    if (!res.ok) return;
    listEl.replaceChildren(...res.value.map((task) => createTaskItem(task)));
    updateEmptyMessage();
}

// ===== Bulk add =====
const bulkForm = document.getElementById("bulk-form");
const bulkInput = document.getElementById("bulk-input");
const bulkCount = document.getElementById("bulk-count");
const MAX_BULK = 50;

// One task per line: trim spaces and ignore empty lines
function getBulkTitles() {
    return bulkInput.value
        .split("\n")
        .map((line) => line.trim())
        .filter(Boolean);
}

function updateBulkCount() {
    const count = getBulkTitles().length;
    const tooMany = count > MAX_BULK;
    bulkCount.textContent = tooMany
        ? `${count} tasks - the maximum is ${MAX_BULK}`
        : `${count} ${count === 1 ? "task" : "tasks"} will be added`;
    bulkCount.classList.toggle("over", tooMany);
}

bulkInput.addEventListener("input", updateBulkCount);

bulkForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const titles = getBulkTitles();
    if (titles.length === 0) {
        showError("Type at least one task (one per line).");
        return;
    }
    if (titles.length > MAX_BULK) {
        showError(`You can add at most ${MAX_BULK} tasks at once.`);
        return;
    }

    const res = await run(() => Api.createTasksBulk(titles), `Adding ${titles.length} tasks…`);
    if (res.ok) {
        // created[0] is the first line you typed: add from the last so it ends up on top
        for (let i = res.value.length - 1; i >= 0; i--) {
            listEl.prepend(createTaskItem(res.value[i]));
        }
        updateEmptyMessage();
        bulkInput.value = "";
        updateBulkCount();
    }
});

updateBulkCount();

loadTasks();

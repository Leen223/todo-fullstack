

const errorEl = document.getElementById("error");
const usersBody = document.getElementById("users-body");
const tasksBody = document.getElementById("tasks-body");

Auth.requireLogin();
document.getElementById("logout-btn").addEventListener("click", () => Auth.logout());

function showError(message) {
    errorEl.textContent = message;
    errorEl.hidden = !message;
}

function cell(text) {
    const td = document.createElement("td");
    td.textContent = text;
    return td;
}

function renderUsers(users, myId) {
    usersBody.replaceChildren();

    users.forEach((user) => {
        const row = document.createElement("tr");

        const roleCell = document.createElement("td");
        const badge = document.createElement("span");
        badge.className = "badge";
        badge.textContent = user.role;
        roleCell.append(badge);

        const actionCell = document.createElement("td");
        if (user.id !== myId) {
            const deleteBtn = document.createElement("button");
            deleteBtn.type = "button";
            deleteBtn.className = "table-btn";
            deleteBtn.textContent = "Delete";
            deleteBtn.addEventListener("click", () => deleteUser(user));
            actionCell.append(deleteBtn);
        }

        row.append(cell(user.id), cell(user.name), cell(user.email), roleCell, cell(user.created_at), actionCell);
        usersBody.append(row);
    });
}

function renderTasks(tasks) {
    tasksBody.replaceChildren();

    if (tasks.length === 0) {
        const row = document.createElement("tr");
        const td = cell("No tasks yet.");
        td.colSpan = 4;
        row.append(td);
        tasksBody.append(row);
        return;
    }

    tasks.forEach((task) => {
        const row = document.createElement("tr");
        row.append(
            cell(task.title),
            cell(task.owner_name ?? "(no owner)"),
            cell(task.is_completed ? "Done" : "Open"),
            cell(task.created_at)
        );
        tasksBody.append(row);
    });
}

async function deleteUser(user) {
    if (!confirm(`Delete ${user.name} and all their tasks?`)) return;

    try {
        showError("");
        await Api.adminDeleteUser(user.id);
        await load();
    } catch (err) {
        showError(err.message);
    }
}

async function load() {
    try {
        showError("");
        const me = await Api.me();
        Auth.setUser(me);

        
        if (me.role !== "admin") {
            window.location.replace("index.html");
            return;
        }
        document.getElementById("admin-name").textContent = me.name;

        const [users, tasks] = await Promise.all([Api.adminUsers(), Api.adminTasks()]);
        renderUsers(users, me.id);
        renderTasks(tasks);
    } catch (err) {
        showError(err.message);
    }
}

load();

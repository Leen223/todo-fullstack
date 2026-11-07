const API_BASE = window.API_BASE || "api";

async function request(path, options = {}) {
    let response;
    try {
        response = await fetch(API_BASE + path, {
            headers: { "Content-Type": "application/json" },
            ...options,
        });
    } catch (err) {
        throw new Error("Cannot reach the server. Please check your connection and try again.");
    }

    if (response.status === 204) return null;

    let data = null;
    try {
        data = await response.json();
    } catch {

    }

    if (!response.ok) {
        throw new Error((data && data.error) || "Something went wrong. Please try again.");
    }
    return data;
}

const Api = {
    getTasks: () => request("/tasks"),
    getTask: (id) => request(`/tasks/${id}`),
    createTask: (title) => request("/tasks", { method: "POST", body: JSON.stringify({ title }) }),
    updateTask: (id, changes) => request(`/tasks/${id}`, { method: "PUT", body: JSON.stringify(changes) }),
    deleteTask: (id) => request(`/tasks/${id}`, { method: "DELETE" }),
};

const API_BASE = window.API_BASE || "api";
const TOKEN_KEY = "todo_token";
const USER_KEY = "todo_user";


const Auth = {
    getToken() {
        return localStorage.getItem(TOKEN_KEY);
    },
    getUser() {
        try {
            return JSON.parse(localStorage.getItem(USER_KEY));
        } catch {
            return null;
        }
    },
    save(token, user) {
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, JSON.stringify(user));
    },
    setUser(user) {
        localStorage.setItem(USER_KEY, JSON.stringify(user));
    },
    clear() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
    },
    logout() {
        this.clear();
        window.location.replace("login.html");
    },
    
    requireLogin() {
        if (!this.getToken()) {
            window.location.replace("login.html");
            return false;
        }
        return true;
    },
};


async function request(path, options = {}) {
    const { auth = true, ...fetchOptions } = options;

    const headers = { "Content-Type": "application/json" };
    const token = Auth.getToken();
    if (auth && token) {
        headers.Authorization = `Bearer ${token}`;
    }

    let response;
    try {
        response = await fetch(API_BASE + path, { ...fetchOptions, headers });
    } catch (err) {
        throw new Error("Cannot reach the server. Please check your connection and try again.");
    }

   
    if (response.status === 401 && auth) {
        Auth.clear();
        window.location.replace("login.html");
        throw new Error("Your session has expired. Please log in again.");
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
    
    register: (name, email, password) =>
        request("/auth/register", { method: "POST", auth: false, body: JSON.stringify({ name, email, password }) }),
    login: (email, password) =>
        request("/auth/login", { method: "POST", auth: false, body: JSON.stringify({ email, password }) }),
    me: () => request("/auth/me"),

    
    adminUsers: () => request("/admin/users"),
    adminTasks: () => request("/admin/tasks"),
    adminDeleteUser: (id) => request(`/admin/users/${id}`, { method: "DELETE" }),

    
    getTasks: () => request("/tasks"),
    getTask: (id) => request(`/tasks/${id}`),
    createTask: (title) => request("/tasks", { method: "POST", body: JSON.stringify({ title }) }),
    updateTask: (id, changes) => request(`/tasks/${id}`, { method: "PUT", body: JSON.stringify(changes) }),
    deleteTask: (id) => request(`/tasks/${id}`, { method: "DELETE" }),
    createTasksBulk: (titles) => request("/tasks/bulk", { method: "POST", body: JSON.stringify({ titles }) }),
};

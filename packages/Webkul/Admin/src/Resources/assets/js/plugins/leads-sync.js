/**
 * Cross-tab sync for lead list/pipeline when a lead is updated elsewhere
 * (e.g. Lead Details opened in another tab).
 */
import axios from "axios";

const CHANNEL_NAME = "crm-leads-sync";
const STORAGE_KEY = "crm-leads-sync-ping";

const mutatingMethods = new Set(["post", "put", "patch", "delete"]);

function createChannel() {
    try {
        if (typeof BroadcastChannel !== "undefined") {
            return new BroadcastChannel(CHANNEL_NAME);
        }
    } catch (e) {
        // ignore
    }

    return null;
}

const channel = createChannel();
const subscribers = new Set();

function notify(payload = {}) {
    const message = {
        type: "lead-updated",
        at: Date.now(),
        ...payload,
    };

    try {
        channel?.postMessage(message);
    } catch (e) {
        // ignore
    }

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(message));
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
        // ignore
    }
}

function isLeadMutatingRequest(config = {}) {
    const method = String(config.method || "").toLowerCase();

    if (! mutatingMethods.has(method)) {
        return false;
    }

    const url = String(config.url || "");

    return /\/admin\/leads(\/|$|\?)/i.test(url) || /admin\/leads(\/|$|\?)/i.test(url);
}

function dispatchToSubscribers(message) {
    subscribers.forEach((handler) => {
        try {
            handler(message);
        } catch (e) {
            console.error(e);
        }
    });
}

if (channel) {
    channel.onmessage = (event) => {
        if (event?.data?.type === "lead-updated") {
            dispatchToSubscribers(event.data);
        }
    };
}

window.addEventListener("storage", (event) => {
    if (event.key !== STORAGE_KEY || ! event.newValue) {
        return;
    }

    try {
        const message = JSON.parse(event.newValue);

        if (message?.type === "lead-updated") {
            dispatchToSubscribers(message);
        }
    } catch (e) {
        // ignore
    }
});

axios.interceptors.response.use(
    (response) => {
        if (isLeadMutatingRequest(response.config)) {
            notify({ url: response.config?.url });
        }

        return response;
    },
    (error) => Promise.reject(error),
);

window.crmLeadsSync = {
    notify,
    subscribe(handler) {
        if (typeof handler !== "function") {
            return () => {};
        }

        subscribers.add(handler);

        return () => subscribers.delete(handler);
    },
};

export default {
    install() {
        // Side effects above register global sync helpers.
    },
};

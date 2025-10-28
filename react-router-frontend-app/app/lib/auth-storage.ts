export type AuthUser = {
  id: number;
  name: string;
  email: string;
};

const TOKEN_KEY = "anchorless-auth-token";
const USER_KEY = "anchorless-auth-user";

let inMemoryToken: string | null = null;
let inMemoryUser: AuthUser | null = null;

export function loadToken(): string | null {
  if (typeof window === "undefined") return null;

  if (inMemoryToken) return inMemoryToken;

  try {
    inMemoryToken = window.sessionStorage.getItem(TOKEN_KEY);
    return inMemoryToken;
  } catch {
    return null;
  }
}

export function loadUser(): AuthUser | null {
  if (typeof window === "undefined") return null;

  if (inMemoryUser) return inMemoryUser;

  try {
    const raw = window.sessionStorage.getItem(USER_KEY);
    inMemoryUser = raw ? (JSON.parse(raw) as AuthUser) : null;
    return inMemoryUser;
  } catch {
    return null;
  }
}

export function saveSession(token: string, user: AuthUser): void {
  if (typeof window === "undefined") return;

  inMemoryToken = token;
  inMemoryUser = user;

  try {
    window.sessionStorage.setItem(TOKEN_KEY, token);
    window.sessionStorage.setItem(USER_KEY, JSON.stringify(user));
  } catch {
    // ignore storage errors (private mode, etc.)
  }
}

export function saveUser(user: AuthUser): void {
  if (typeof window === "undefined") return;

  inMemoryUser = user;

  try {
    window.sessionStorage.setItem(USER_KEY, JSON.stringify(user));
  } catch {
    // ignore
  }
}

export function clearSession(): void {
  if (typeof window === "undefined") return;

  inMemoryToken = null;
  inMemoryUser = null;

  try {
    window.sessionStorage.removeItem(TOKEN_KEY);
    window.sessionStorage.removeItem(USER_KEY);
  } catch {
    // ignore
  }
}

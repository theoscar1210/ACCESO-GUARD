export type User = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    avatar_url: string | null;
    cedula: string;
    phone: string | null;
    username: string;
    email: string;
    role: string;
    roles: string[];
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};

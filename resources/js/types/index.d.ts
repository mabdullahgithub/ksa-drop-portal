export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    avatar?: string;
    two_factor_enabled: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        permissions: string[];
        roles: string[];
        portal_features?: string[];
        impersonating?: {
            client_id: number;
            client_name: string;
        } | null;
        client?: {
            id: number;
            company_name: string;
            logo?: string;
            client_id: string;
            contact_person?: string;
            phone?: string;
            secondary_phone?: string;
            address?: string;
            city?: string;
            country?: string;
            postal_code?: string;
            tax_id?: string;
            commercial_registration?: string;
            type_label: string;
            is_dropshipper: boolean;
            is_fulfilment: boolean;
            status: string;
        } | null;
    };
    flash: {
        success?: string;
    };
};

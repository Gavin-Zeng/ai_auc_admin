import {
    index as applicationsIndex,
    store as applicationsStore,
    update as applicationsUpdate,
} from '@/routes/applications';
import { index as gamesIndex } from '@/routes/games';
import {
    index as menusIndex,
    store as menusStore,
    update as menusUpdate,
} from '@/routes/menus';
import {
    index as rolesIndex,
    store as rolesStore,
    update as rolesUpdate,
} from '@/routes/roles';
import {
    index as tenantsIndex,
    store as tenantsStore,
    update as tenantsUpdate,
} from '@/routes/tenants';
import {
    index as usersIndex,
    store as usersStore,
    update as usersUpdate,
} from '@/routes/users';

export type ResourceName =
    | 'applications'
    | 'games'
    | 'menus'
    | 'roles'
    | 'tenants'
    | 'users';

type ResourceRouteSet = {
    index: (query?: Record<string, number | string | undefined>) => string;
    store?: () => string;
    update?: (id: number | string) => string;
};

export const resourceRoutes: Record<ResourceName, ResourceRouteSet> = {
    applications: {
        index: (query) => applicationsIndex({ query }).url,
        store: () => applicationsStore().url,
        update: (id) => applicationsUpdate(id).url,
    },
    games: {
        index: (query) => gamesIndex({ query }).url,
    },
    menus: {
        index: (query) => menusIndex({ query }).url,
        store: () => menusStore().url,
        update: (id) => menusUpdate(id).url,
    },
    roles: {
        index: (query) => rolesIndex({ query }).url,
        store: () => rolesStore().url,
        update: (id) => rolesUpdate(id).url,
    },
    tenants: {
        index: (query) => tenantsIndex({ query }).url,
        store: () => tenantsStore().url,
        update: (id) => tenantsUpdate(id).url,
    },
    users: {
        index: (query) => usersIndex({ query }).url,
        store: () => usersStore().url,
        update: (id) => usersUpdate(id).url,
    },
};

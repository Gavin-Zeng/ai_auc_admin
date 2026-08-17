export type ResourceColumn = {
    label: string;
    minWidth?: number;
    width?: number;
    align?: 'left' | 'center' | 'right';
    kind?: 'boolean' | 'date' | 'datetime' | 'number' | 'status' | 'text';
    tooltip?: boolean;
    muted?: boolean;
};

const labels: Record<string, string> = {
    account: '账号',
    application_id: '所属系统',
    application_name: '所属系统',
    applications_text: '已开通系统',
    base_url: '基础地址',
    client_id: '客户端 ID',
    code: '编码',
    company_name: '所属公司',
    company_names: '所属公司',
    created_at: '创建时间',
    description: '描述',
    domain: '域名',
    email: '邮箱',
    group: '分组',
    href: '链接',
    id: 'ID',
    ip_address: 'IP 地址',
    is_company_admin: '公司超管',
    is_owner: '公司超管',
    is_platform_admin: '平台超管',
    is_system: '系统内置',
    is_visible: '是否显示',
    menus_text: '菜单权限',
    name: '名称',
    operated_at: '操作时间',
    operation_action: '操作动作',
    operation_object: '操作对象',
    operator_name: '操作人',
    parent_id: '父级菜单',
    parent_name: '父级菜单',
    path: '菜单路径',
    redirect_uri: '回调地址',
    request_params: '请求参数',
    role_name: '角色',
    sort_order: '排序',
    status: '状态',
    subject_id: '对象 ID',
    subject_type: '对象类型',
    system_name: '所属系统',
    title: '标题',
    urls_count: '地址数量',
    users_count: '成员数量',
};

const resourceLabels: Record<string, string> = {
    'games.app_id': '子游戏 app_id',
    'games.name': '子游戏名',
    'games.old_id': '母游戏 id',
    'games.old_name': '母游戏名',
    'games.pkg_name': '包名',
};

const booleanColumns = new Set([
    'is_company_admin',
    'is_owner',
    'is_platform_admin',
    'is_system',
    'is_visible',
]);
const numberColumns = new Set(['sort_order', 'urls_count', 'users_count']);
const dateTimeColumns = new Set(['created_at', 'operated_at', 'updated_at']);
const longTextColumns = new Set([
    'applications_text',
    'base_url',
    'description',
    'href',
    'menus_text',
    'path',
    'redirect_uri',
    'request_params',
]);

export function resourceColumn(resource: string, name: string): ResourceColumn {
    const kind =
        name === 'status'
            ? 'status'
            : booleanColumns.has(name)
              ? 'boolean'
              : numberColumns.has(name)
                ? 'number'
                : dateTimeColumns.has(name)
                  ? 'datetime'
                  : 'text';

    return {
        label: resourceLabels[`${resource}.${name}`] ?? labels[name] ?? name,
        kind,
        align:
            kind === 'number'
                ? 'right'
                : kind === 'status' || kind === 'boolean'
                  ? 'center'
                  : 'left',
        width: name === 'status' ? 92 : name === 'id' ? 80 : undefined,
        minWidth: longTextColumns.has(name)
            ? 180
            : name.includes('_at')
              ? 168
              : 120,
        tooltip: longTextColumns.has(name),
        muted: name === 'id',
    };
}

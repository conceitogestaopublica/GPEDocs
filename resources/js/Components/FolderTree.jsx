/**
 * FolderTree — Componente de árvore de pastas recursiva
 *
 * Props:
 *   - folders: Array<{ id, nome, children, parent_id }>
 *   - selectedId: number|null — pasta selecionada
 *   - onSelect: function(id) — callback ao clicar na pasta
 *   - onContextMenu: function(id, event) — callback para menu de contexto
 */
import { useState } from 'react';

function FolderNode({ folder, selectedId, onSelect, onContextMenu, level = 0 }) {
    const [expanded, setExpanded] = useState(false);
    const hasChildren = folder.children && folder.children.length > 0;
    const isSelected = selectedId === folder.id;

    const handleToggle = (e) => {
        e.stopPropagation();
        setExpanded(!expanded);
    };

    const handleClick = () => {
        onSelect?.(folder.id);
        if (hasChildren && !expanded) setExpanded(true);
    };

    const handleContext = (e) => {
        e.preventDefault();
        onContextMenu?.(folder.id, e);
    };

    return (
        <div>
            <div
                onClick={handleClick}
                onContextMenu={handleContext}
                className={`flex items-center gap-1.5 px-2 py-1.5 rounded-lg cursor-pointer text-sm transition-colors select-none
                    ${isSelected ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-100'}`}
                style={{ paddingLeft: `${level * 16 + 8}px` }}
            >
                {/* Chevron para expandir/recolher */}
                <button
                    onClick={handleToggle}
                    className={`w-5 h-5 flex items-center justify-center rounded transition-transform ${hasChildren ? '' : 'invisible'}`}
                >
                    <i className={`fas fa-chevron-right text-[10px] text-gray-400 transition-transform ${expanded ? 'rotate-90' : ''}`} />
                </button>

                {/* Ícone da pasta */}
                <i className={`fas ${expanded && hasChildren ? 'fa-folder-open text-yellow-500' : 'fa-folder text-yellow-400'} text-sm`} />

                {/* Nome da pasta */}
                <span className="truncate">{folder.nome}</span>
            </div>

            {/* Sub-pastas */}
            {hasChildren && expanded && (
                <div>
                    {folder.children.map((child) => (
                        <FolderNode
                            key={child.id}
                            folder={child}
                            selectedId={selectedId}
                            onSelect={onSelect}
                            onContextMenu={onContextMenu}
                            level={level + 1}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function FolderTree({ folders = [], selectedId = null, onSelect, onContextMenu }) {
    if (folders.length === 0) {
        return (
            <div className="text-sm text-gray-400 text-center py-4">
                <i className="fas fa-folder-open text-2xl mb-2 block" />
                Nenhuma pasta encontrada
            </div>
        );
    }

    return (
        <div className="space-y-0.5">
            {folders.map((folder) => (
                <FolderNode
                    key={folder.id}
                    folder={folder}
                    selectedId={selectedId}
                    onSelect={onSelect}
                    onContextMenu={onContextMenu}
                />
            ))}
        </div>
    );
}

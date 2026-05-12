<?php
/**
 * Ayudante de Paginación para Barbería
 */

/**
 * Calcula los parámetros de paginación
 * @param mysqli $conn Conexión a la base de datos
 * @param string $query Consulta SQL para contar (ej: "SELECT COUNT(*) as total FROM cliente")
 * @param int $limit Elementos por página
 * @return array [offset, total_pages, current_page, total_records]
 */
function getPaginationData($conn, $countQuery, $limit = 10, $pageVar = 'p') {
    $page = isset($_GET[$pageVar]) ? max(1, intval($_GET[$pageVar])) : 1;
    
    $result = $conn->query($countQuery);
    $total_records = $result->fetch_assoc()['total'] ?? 0;
    $total_pages = ceil($total_records / $limit);
    
    // Asegurar que la página no exceda el total
    if ($total_pages > 0 && $page > $total_pages) {
        $page = $total_pages;
    }
    
    $offset = ($page - 1) * $limit;
    
    return [
        'offset' => $offset,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'total_records' => $total_records
    ];
}

/**
 * Renderiza el HTML de la paginación
 */
function renderPagination($currentPage, $totalPages, $urlParams = '', $pageVar = 'p', $anchor = '') {
    if ($totalPages <= 1) return '';

    $html = '<div class="pagination-container">';
    $html .= '<div class="pagination">';
    
    // Limpiar urlParams para que no duplique el parámetro de página actual
    $urlParams = preg_replace('/([?&])' . preg_quote($pageVar) . '=\d+(&?)/', '$1', $urlParams);
    $urlParams = rtrim($urlParams, '&?');
    $connector = (strpos($urlParams, '?') === false) ? '?' : '&';

    // Botón Anterior
    if ($currentPage > 1) {
        $html .= '<a href="' . $urlParams . $connector . $pageVar . '=' . ($currentPage - 1) . $anchor . '" class="page-link prev">&laquo; Anterior</a>';
    } else {
        $html .= '<span class="page-link disabled">&laquo; Anterior</span>';
    }

    // Páginas
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        $html .= '<a href="' . $urlParams . $connector . $pageVar . '=1' . $anchor . '" class="page-link">1</a>';
        if ($start > 2) $html .= '<span class="page-dots">...</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $html .= '<a href="' . $urlParams . $connector . $pageVar . '=' . $i . $anchor . '" class="page-link ' . $active . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<span class="page-dots">...</span>';
        $html .= '<a href="' . $urlParams . $connector . $pageVar . '=' . $totalPages . $anchor . '" class="page-link">' . $totalPages . '</a>';
    }

    // Botón Siguiente
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $urlParams . $connector . $pageVar . '=' . ($currentPage + 1) . $anchor . '" class="page-link next">Siguiente &raquo;</a>';
    } else {
        $html .= '<span class="page-link disabled">Siguiente &raquo;</span>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * CSS para la paginación (se puede incluir en el header o inline)
 */
function getPaginationStyles() {
    return '
        .pagination-container {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .page-link {
            padding: 8px 14px;
            border-radius: 8px;
            background: white;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #e0e0e0;
            transition: all 0.2s;
        }
        .page-link:hover:not(.disabled):not(.active) {
            background: #f0f2ff;
            border-color: #667eea;
            transform: translateY(-1px);
        }
        .page-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        .page-link.disabled {
            color: #ccc;
            cursor: not-allowed;
            background: #f9f9f9;
        }
        .page-dots {
            color: #999;
            padding: 0 5px;
        }
        .prev, .next {
            min-width: 100px;
            text-align: center;
        }
    ';
}

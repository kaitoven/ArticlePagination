<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 文章分页插件:
 * 支持文章分页模式切换，并记录用户选择
 * 
 * @package ArticlePagination
 * @author Kaitoven Chen
 * @version 1.0.2
 * @link https://www.chendk.info
 */
class ArticlePagination_Plugin implements Typecho_Plugin_Interface
{
    // 插件激活时调用
    public static function activate()
    {
        // 在文章页面添加分页按钮
        Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('ArticlePagination_Plugin', 'addPaginationButton');
        return _t('文章分页插件已激活');
    }

    // 插件停用时调用
    public static function deactivate()
    {
        return _t('文章分页插件已禁用');
    }

    // 插件配置面板（如果需要）
    public static function config(Typecho_Widget_Helper_Form $form) {}

    // 个人用户配置面板
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    // 卸载插件时调用
    public static function uninstall() {}

    // 在文章页面添加悬浮分页按钮
    public static function addPaginationButton($archive)
    {
        // 检查是否是 PDF 预览请求
        if ($archive->request->get('pdf_preview') == 1) {
            // 如果是 PDF 预览请求，直接返回，禁用分页功能
            return;
        }

        if ($archive->is('post')) {
            // 插入CSS样式
            echo '<style>
                #pagination-controls {
                    position: fixed;
                    bottom: 0px;
                    left: 50%;
                    transform: translateX(-50%);
                    background-color: rgba(0, 0, 0, 0.6);
                    color: white;
                    padding: 5px 10px;
                    border-radius: 20px;
                    z-index: 1000;
                    display: flex;
                    gap: 5px;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                }
                #pagination-controls button {
                    background-color: #333;
                    color: white;
                    border: none;
                    padding: 5px 10px;
                    cursor: pointer;
                    border-radius: 10px;
                    transition: background-color 0.3s;
                }
                #pagination-controls button:disabled {
                    background-color: #aaa;
                    cursor: not-allowed;
                }
                #pagination-controls button:hover:not(:disabled) {
                    background-color: #555;
                }
                .page {
                    display: none;
                    opacity: 0;
                    transition: opacity 0.5s ease-in-out;
                }
                .page.active {
                    display: block;
                    opacity: 1;
                }
                .loader {
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    width: 30px;
                    height: 30px;
                    animation: spin 1s linear infinite;
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>';

            // 插入分页逻辑的 JavaScript
            echo '<script type="text/javascript">
                window.addEventListener("load", function() {
                    // 创建分页按钮
                    var paginationButton = document.createElement("a");
                    paginationButton.href = "javascript:void(0);";
                    paginationButton.title = "Switch pagination mode";
                    paginationButton.style.position = "fixed";
                    paginationButton.style.bottom = "90px";
                    paginationButton.style.right = "13px";
                    paginationButton.style.color = "white";
                    paginationButton.style.padding = "15px";
                    paginationButton.style.borderRadius = "50%";
                    // paginationButton.style.backgroundColor = "rgba(0, 0, 0, 0.7)";
                    paginationButton.style.zIndex = "1000";

                    var img = document.createElement("img");
                    // img.src = "https://www.chendk.info/usr/themes/jasmine/pictures/pagebreak.svg";
                    img.src = "https://www.chendk.info/usr/themes/jasmine/pictures/s1.png";
                    img.alt = "分页模式";
                    img.style.width = "auto";
                    img.style.height = "40px";

                    paginationButton.appendChild(img);
                    paginationButton.onclick = togglePagination;

                    document.body.appendChild(paginationButton);

                    // 检查 LocalStorage 中是否有存储的分页模式
                    const savedMode = localStorage.getItem("paginationMode");
                    if (savedMode === "enabled") {
                        // 如果用户之前选择了分页模式，自动应用
                        togglePagination();
                    }
                });

                // 分页切换函数
                function togglePagination() {
                    const articleContent = document.querySelector(".post-content");
                    
                    // 检查是否存在 .post-content
                    if (!articleContent) {
                        console.error("未找到 class=\'post-content\' 的文章内容区域");
                        return;
                    }

                    const isPaginated = articleContent.classList.contains("paginated");
                    if (isPaginated) {
                        articleContent.classList.remove("paginated");
                        removePagination();
                        // 移除 LocalStorage 中的分页模式
                        localStorage.removeItem("paginationMode");
                    } else {
                        articleContent.classList.add("paginated");
                        addPagination();
                        // 存储用户的分页模式到 LocalStorage
                        localStorage.setItem("paginationMode", "enabled");
                    }
                }

                function addPagination() {
                    const content = document.querySelector(".post-content");
                    const allElements = Array.from(content.children);
                    const pageHeight = window.innerHeight * 0.8;
                    let currentPageHeight = 0;
                    let currentPage = [];
                    let pageIndex = 1;

                    // 获取原始内容，保存在内存中以便退出分页模式时恢复
                    const originalContent = content.innerHTML;

                    // 清空内容，准备插入分页内容
                    content.innerHTML = "";

                    allElements.forEach(element => {
                        const cloneElement = element.cloneNode(true);
                        content.appendChild(cloneElement);
                        const elementHeight = cloneElement.offsetHeight;

                        if (elementHeight > pageHeight) {
                            if (currentPage.length > 0) {
                                createPage(currentPage, pageIndex);
                                currentPage = [];
                                currentPageHeight = 0;
                                pageIndex++;
                            }
                            createPage([cloneElement], pageIndex);
                            pageIndex++;
                        } else if (currentPageHeight + elementHeight > pageHeight) {
                            createPage(currentPage, pageIndex);
                            currentPage = [];
                            currentPageHeight = 0;
                            pageIndex++;
                            currentPage.push(cloneElement);
                            currentPageHeight += elementHeight;
                        } else {
                            currentPage.push(cloneElement);
                            currentPageHeight += elementHeight;
                        }
                    });

                    if (currentPage.length > 0) {
                        createPage(currentPage, pageIndex);
                    }

                    createPaginationControls(pageIndex);
                    changePage(0);

                    // 将原始内容保存到全局变量，便于退出分页模式时恢复
                    window.originalPostContent = originalContent;
                }

                function createPage(elements, pageIndex) {
                    const pageDiv = document.createElement("div");
                    pageDiv.classList.add("page");
                    pageDiv.dataset.page = pageIndex;

                    elements.forEach(element => {
                        pageDiv.appendChild(element);
                    });

                    document.querySelector(".post-content").appendChild(pageDiv);
                }

                function removePagination() {
                    // 移除分页内容并恢复原始内容
                    const content = document.querySelector(".post-content");
                    content.innerHTML = window.originalPostContent;

                    // 移除分页控件
                    const paginationControls = document.getElementById("pagination-controls");
                    if (paginationControls) {
                        paginationControls.remove();
                    }
                }

                function createPaginationControls(totalPages) {
                    const paginationControls = document.createElement("div");
                    paginationControls.id = "pagination-controls";
                    paginationControls.innerHTML = `
                        <button id="prevPage" onclick="changePage(-1)">Prev</button>
                        <span id="page-number">1/${totalPages}</span>
                        <button id="nextPage" onclick="changePage(1)">Next</button>
                    `;
                    document.body.appendChild(paginationControls);

                    updateButtonState();
                }

                let currentPage = 1;

                function changePage(direction) {
                    const loader = document.createElement("div");
                    loader.classList.add("loader");
                    document.body.appendChild(loader);

                    const pages = document.querySelectorAll(".post-content .page");
                    const totalPages = pages.length;

                    currentPage += direction;
                    if (currentPage < 1) currentPage = 1;
                    if (currentPage > totalPages) currentPage = totalPages;

                    pages.forEach(page => {
                        page.classList.remove("active");
                        page.style.display = "none";
                    });

                    const currentPageElement = pages[currentPage - 1];
                    currentPageElement.classList.add("active");
                    currentPageElement.style.display = "block";
                    setTimeout(() => {
                        currentPageElement.style.opacity = 1;
                        loader.remove();
                    }, 100);

                    window.scrollTo({ top: 0, behavior: "smooth" });
                    document.querySelector("#page-number").textContent = `${currentPage}/${totalPages}`;

                    updateButtonState();
                }

                function updateButtonState() {
                    const totalPages = document.querySelectorAll(".post-content .page").length;
                    const prevButton = document.getElementById("prevPage");
                    const nextButton = document.getElementById("nextPage");

                    // 根据当前页禁用或启用按钮
                    if (currentPage === 1) {
                        prevButton.disabled = true;
                    } else {
                        prevButton.disabled = false;
                    }

                    if (currentPage === totalPages) {
                        nextButton.disabled = true;
                    } else {
                        nextButton.disabled = false;
                    }
                }
            </script>';
        }
    }
}
?>

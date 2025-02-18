<?php
global $db;
$errors = [];

/** Add category **/
if (isset($_POST['categoryAdd'])) {
    $catName = $_POST['AddCategoryName'];
    $catParent = $_POST['AddCategoryParent'];

    if (!empty($catName)) {

        if (strlen($catName) > 100) { array_push($errors, "Category name is too long. Max 100 characters."); }

        if (count($errors) == 0) {
            // Insert category
            $stmt = mysqli_prepare($db, "INSERT INTO categories(parent_id, category_name) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "is", $catParent, $catName);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        array_push($errors, "Fill required fields");
    }
}



/** Delete category **/
if (isset($_GET['deleteCatID'])) {

    $deleteCatID = $_GET['deleteCatID'];
    if (is_numeric($deleteCatID)) {
        // 1. Check if this category is a parent of other categories
        $isParentQuery = mysqli_query($db, "SELECT category_id FROM categories WHERE parent_id = $deleteCatID");

        if (mysqli_num_rows($isParentQuery) > 0) {
            // 2. If yes - check if it's a first category in this tree
            $isFirstCatQuery = mysqli_query($db, "SELECT parent_id FROM categories WHERE category_id = $deleteCatID");

            // 3. If first - edit it's children parent_id to 0
            $parentID = mysqli_fetch_array($isFirstCatQuery)[0];
            if ($parentID == 0) {
                mysqli_query($db, "UPDATE categories SET parent_id = 0 WHERE parent_id = $deleteCatID");

            } else {
                // 4. If not first - get it's parent_id and change it's children parent_id to it
                mysqli_query($db, "UPDATE categories SET parent_id = $parentID WHERE parent_id = $deleteCatID");

            }
        }

        // Delete category from product_category table
        mysqli_query($db, "DELETE FROM product_category WHERE category_id = $deleteCatID");

        // 5. Delete category
        mysqli_query($db, "DELETE FROM categories WHERE category_id = $deleteCatID");

    } else {
        array_push($errors, "Given category ID isn't a numeric value.");
    }
}

?>


<div class="row">


        <?php
        displayErrors($errors);
        ?>



    <div class="col-xl-5 mb-3 mb-xl-0">
        <h2>Add new category</h2>
        <form method="post" action="index.php?source=categories">
            <div class="mb-3">
                <div class="d-flex flex-row"><label for="AddCategoryName" class="form-label">Category name</label><div class="required">*</div></div>
                <input type="text" class="form-control" id="AddCategoryName" name="AddCategoryName" maxlength="100" aria-describedby="categoryNameHelp">
                <div id="categoryNameHelp" class="form-text">Category name is the one visible on website.</div>
            </div>

            <div class="mb-3">
                <label for="AddCategoryParent" class="form-label">Choose parent category</label>
                <select class="form-select" id="AddCategoryParent" name="AddCategoryParent" aria-describedby="parentHelp">
                    <option selected value="0">None</option>
                    <?php
                    /** Get categories from database and display them in select field **/
                    categoriesHierarchyInSelectField($db,0);
                    ?>
                </select>
                <div id="parentHelp" class="form-text">Choose parent category to create hierarchy.</div>
            </div>

            <button type="submit" class="btn btn-primary " name="categoryAdd">Submit</button>
        </form>
    </div>




    <div class="col-xl-7">
        <h2>Categories</h2>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Products</th>
                        <th scope="col">Edit</th>
                        <th scope="col">Delete</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                function categoriesHierarchy($parentID = 0, $hierarchy = '') {
                    global $db;
                    $categoriesQuery = mysqli_query($db, "SELECT category_id, category_name FROM categories WHERE parent_id = $parentID ORDER BY category_name ASC");

                    if (mysqli_num_rows($categoriesQuery) > 0) {
                        while($categoriesResult = mysqli_fetch_assoc($categoriesQuery)) {
                            $curCatID = $categoriesResult['category_id'];
                            $productCountQuery = mysqli_query($db, "SELECT product_id FROM product_category WHERE category_id = $curCatID");
                            $productCount = mysqli_num_rows($productCountQuery);
                            ?>
                            <tr>
                                <td><?php echo $hierarchy.' '.$categoriesResult['category_name']; ?></td>
                                <td><?=$productCount?></td>
                                <td><a href="index.php?source=categories&editCatID=<?php echo $categoriesResult['category_id'] ?>">Edit</a></td>
                                <td><a href="index.php?source=categories&deleteCatID=<?php echo $categoriesResult['category_id'] ?>" class="link-danger delete-category-link" data-bs-toggle="modal" data-bs-target="#modalCatDeleteWarning">Delete</a></td>
                            </tr>
                            <?php
                            categoriesHierarchy($categoriesResult['category_id'], $hierarchy.'—');
                        }
                    }
                }
                categoriesHierarchy();

                ?>
                </tbody>
            </table>
        </div>
    </div>

</div>




<!-- Modal - delete category -->
<div class="modal fade" id="modalCatDeleteWarning" tabindex="-1" aria-labelledby="modalCatDeleteWarningLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCatDeleteWarningLabel">Delete category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this category? This operation cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="deleteCategoryConfirm">Delete category</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.onload = function() { deleteAndShowModal('delete-category-link', 'deleteCategoryConfirm') };
</script>


















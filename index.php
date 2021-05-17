<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover"/>
<meta name="description" content="A mind map editor, showing how subtrees can be moved, copied, deleted, and laid out."/> 
<link rel="stylesheet" href="assets/css/style.css"/> 
<!-- Copyright 1998-2021 by Northwoods Software Corporation. -->
<title>Mind Map</title>
<script src="api/assets/js/FileSaver.js"></script>
</head>

<body>
  <!-- This top nav is not part of the sample code -->
  <nav id="navTop" class="w-full z-30 top-0 text-white bg-nwoods-primary">
    <div class="w-full container max-w-screen-lg mx-auto flex flex-wrap sm:flex-nowrap items-center justify-between mt-0 py-2">
      <div class="md:pl-4">
        <a class="text-white hover:text-white no-underline hover:no-underline
        font-bold text-2xl lg:text-4xl rounded-lg hover:bg-nwoods-secondary " href="../">
          <h1 class="mb-0 p-1 ">Mind Map Challenge BN Based on GoJS</h1>
        </a>
      </div>
    </div>
    <hr class="border-b border-gray-600 opacity-50 my-0 py-0" />
  </nav>
  <div class="md:flex flex-col md:flex-row md:min-h-screen w-full max-w-screen-xl mx-auto">
    <!-- * * * * * * * * * * * * * -->
    <!-- Start of Mind map challenge sample code -->
    
    <script src="release/go.js"></script>
    <div class="p-4 w-full">
    <script id="code">
    function init() {

      var $ = go.GraphObject.make;

      myDiagram =
        $(go.Diagram, "myDiagramDiv",
          {
            // when the user drags a node, also move/copy/delete the whole subtree starting with that node
            "commandHandler.copiesTree": true,
            "commandHandler.copiesParentKey": true,
            "commandHandler.deletesTree": true,
            "draggingTool.dragsTree": true,
            "undoManager.isEnabled": true
          });

      // when the document is modified, add a "*" to the title and enable the "Save" button
      myDiagram.addDiagramListener("Modified", function(e) {
        var button = document.getElementById("SaveButton");
        if (button) button.disabled = !myDiagram.isModified;
        var idx = document.title.indexOf("*");
        if (myDiagram.isModified) {
          if (idx < 0) document.title += "*";
        } else {
          if (idx >= 0) document.title = document.title.substr(0, idx);
        }
      });

      // a node consists of some text with a line shape underneath
      myDiagram.nodeTemplate =
        $(go.Node, "Vertical",
          { selectionObjectName: "TEXT" },
          $(go.TextBlock,
            {
              name: "TEXT",
              minSize: new go.Size(30, 15),
              editable: true
            },
            // remember not only the text string but the scale and the font in the node data
            new go.Binding("text", "text").makeTwoWay(),
            new go.Binding("scale", "scale").makeTwoWay(),
            new go.Binding("font", "font").makeTwoWay()),
          $(go.Shape, "LineH",
            {
              stretch: go.GraphObject.Horizontal,
              strokeWidth: 3, height: 3,
              // this line shape is the port -- what links connect with
              portId: "", fromSpot: go.Spot.LeftRightSides, toSpot: go.Spot.LeftRightSides
            },
            new go.Binding("stroke", "brush"),
            // make sure links come in from the proper direction and go out appropriately
            new go.Binding("fromSpot", "dir", function(d) { return spotConverter(d, true); }),
            new go.Binding("toSpot", "dir", function(d) { return spotConverter(d, false); })),
          // remember the locations of each node in the node data
          new go.Binding("location", "loc", go.Point.parse).makeTwoWay(go.Point.stringify),
          // make sure text "grows" in the desired direction
          new go.Binding("locationSpot", "dir", function(d) { return spotConverter(d, false); })
        );

      // selected nodes show a button for adding children
      myDiagram.nodeTemplate.selectionAdornmentTemplate =
        $(go.Adornment, "Spot",
          $(go.Panel, "Auto",
            // this Adornment has a rectangular blue Shape around the selected node
            $(go.Shape, { fill: null, stroke: "dodgerblue", strokeWidth: 3 }),
            $(go.Placeholder, { margin: new go.Margin(4, 4, 0, 4) })
          ),
          // and this Adornment has a Button to the right of the selected node
          $("Button",
            {
              alignment: go.Spot.Right,
              alignmentFocus: go.Spot.Left,
              click: addNodeAndLink  // define click behavior for this Button in the Adornment
            },
            $(go.TextBlock, "+",  // the Button content
              { font: "bold 8pt sans-serif" })
          )
        );

      // the context menu allows users to change the font size and weight,
      // and to perform a limited tree layout starting at that node
      myDiagram.nodeTemplate.contextMenu =
        $("ContextMenu",
          $("ContextMenuButton",
            $(go.TextBlock, "Bigger"),
            { click: function(e, obj) { changeTextSize(obj, 1.1); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Smaller"),
            { click: function(e, obj) { changeTextSize(obj, 1 / 1.1); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Bold/Normal"),
            { click: function(e, obj) { toggleTextWeight(obj); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Copy"),
            { click: function(e, obj) { e.diagram.commandHandler.copySelection(); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Delete"),
            { click: function(e, obj) { e.diagram.commandHandler.deleteSelection(); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Undo"),
            { click: function(e, obj) { e.diagram.commandHandler.undo(); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Redo"),
            { click: function(e, obj) { e.diagram.commandHandler.redo(); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Layout"),
            {
              click: function(e, obj) {
                var adorn = obj.part;
                adorn.diagram.startTransaction("Subtree Layout");
                layoutTree(adorn.adornedPart);
                adorn.diagram.commitTransaction("Subtree Layout");
              }
            }
          )
        );

      // a link is just a Bezier-curved line of the same color as the node to which it is connected
      myDiagram.linkTemplate =
        $(go.Link,
          {
            curve: go.Link.Bezier,
            fromShortLength: -2,
            toShortLength: -2,
            selectable: false
          },
          $(go.Shape,
            { strokeWidth: 3 },
            new go.Binding("stroke", "toNode", function(n) {
              if (n.data.brush) return n.data.brush;
              return "black";
            }).ofObject())
        );

      // the Diagram's context menu just displays commands for general functionality
      myDiagram.contextMenu =
        $("ContextMenu",
          $("ContextMenuButton",
            $(go.TextBlock, "Paste"),
            { click: function (e, obj) { e.diagram.commandHandler.pasteSelection(e.diagram.toolManager.contextMenuTool.mouseDownPoint); } },
            new go.Binding("visible", "", function(o) { return o.diagram && o.diagram.commandHandler.canPasteSelection(o.diagram.toolManager.contextMenuTool.mouseDownPoint); }).ofObject()),
          $("ContextMenuButton",
            $(go.TextBlock, "Undo"),
            { click: function(e, obj) { e.diagram.commandHandler.undo(); } },
            new go.Binding("visible", "", function(o) { return o.diagram && o.diagram.commandHandler.canUndo(); }).ofObject()),
          $("ContextMenuButton",
            $(go.TextBlock, "Redo"),
            { click: function(e, obj) { e.diagram.commandHandler.redo(); } },
            new go.Binding("visible", "", function(o) { return o.diagram && o.diagram.commandHandler.canRedo(); }).ofObject()),
          $("ContextMenuButton",
            $(go.TextBlock, "Save"),
            { click: function(e, obj) { save(); } }),
          $("ContextMenuButton",
            $(go.TextBlock, "Load"),
            { click: function(e, obj) { load(); } })
        );

      myDiagram.addDiagramListener("SelectionMoved", function(e) {
        var rootX = myDiagram.findNodeForKey(0).location.x;
        myDiagram.selection.each(function(node) {
          if (node.data.parent !== 0) return; // Only consider nodes connected to the root
          var nodeX = node.location.x;
          if (rootX < nodeX && node.data.dir !== "right") {
            updateNodeDirection(node, "right");
          } else if (rootX > nodeX && node.data.dir !== "left") {
            updateNodeDirection(node, "left");
          }
          layoutTree(node);
        });
      });
	  
	  	fetch('mindMapRecord.txt')
		.then(response => response.text())
		.then(text => document.getElementById("mySavedModel").value = text)
		.then(text => myDiagram.model = go.Model.fromJson(text))
		myDiagram.isModified = true;
      // read in the predefined graph using the JSON format data held in the "mySavedModel" textarea
      load();
    }

    function spotConverter(dir, from) {
      if (dir === "left") {
        return (from ? go.Spot.Left : go.Spot.Right);
      } else {
        return (from ? go.Spot.Right : go.Spot.Left);
      }
    }

    function changeTextSize(obj, factor) {
      var adorn = obj.part;
      adorn.diagram.startTransaction("Change Text Size");
      var node = adorn.adornedPart;
      var tb = node.findObject("TEXT");
      tb.scale *= factor;
      adorn.diagram.commitTransaction("Change Text Size");
    }

    function toggleTextWeight(obj) {
      var adorn = obj.part;
      adorn.diagram.startTransaction("Change Text Weight");
      var node = adorn.adornedPart;
      var tb = node.findObject("TEXT");
      // assume "bold" is at the start of the font specifier
      var idx = tb.font.indexOf("bold");
      if (idx < 0) {
        tb.font = "bold " + tb.font;
      } else {
        tb.font = tb.font.substr(idx + 5);
      }
      adorn.diagram.commitTransaction("Change Text Weight");
    }

    function updateNodeDirection(node, dir) {
      myDiagram.model.setDataProperty(node.data, "dir", dir);
      // recursively update the direction of the child nodes
      var chl = node.findTreeChildrenNodes(); // gives us an iterator of the child nodes related to this particular node
      while (chl.next()) {
        updateNodeDirection(chl.value, dir);
      }
    }

    function addNodeAndLink(e, obj) {
      var adorn = obj.part;
      var diagram = adorn.diagram;
      diagram.startTransaction("Add Node");
      var oldnode = adorn.adornedPart;
      var olddata = oldnode.data;
      // copy the brush and direction to the new node data
      var newdata = { text: "idea", brush: olddata.brush, dir: olddata.dir, parent: olddata.key };
      diagram.model.addNodeData(newdata);
      layoutTree(oldnode);
      diagram.commitTransaction("Add Node");

      // if the new node is off-screen, scroll the diagram to show the new node
      var newnode = diagram.findNodeForData(newdata);
      if (newnode !== null) diagram.scrollToRect(newnode.actualBounds);
    }
		
	function addLeafAndLink(idea, abrush, adir, aparent)
	{
		var iparent = parseInt(aparent);
		
		// when the document is modified, add a "*" to the title and enable the "Save" button
		myDiagram.addDiagramListener("Modified", function(e) {
        var button = document.getElementById("SaveButton");
        if (button) button.disabled = !myDiagram.isModified;
        var idx = document.title.indexOf("*");
        if (myDiagram.isModified) {
          if (idx < 0) document.title += "*";
        } else {
          if (idx >= 0) document.title = document.title.substr(0, idx);
        }
      });
	  
	  // copy the brush and direction to the new node data
      var newdata = { text: idea, brush: abrush, dir: adir, parent: iparent };
	  //var newdata = { text: idea, brush: "palevioletred", dir: "right", parent: iparent };
      myDiagram.model.addNodeData(newdata);
      myDiagram.commitTransaction("Add Node");
	}
	
    function layoutTree(node) {
      if (node.data.key === 0) {  // adding to the root?
        layoutAll();  // lay out everything
      } else {  // otherwise lay out only the subtree starting at this parent node
        var parts = node.findTreeParts();
        layoutAngle(parts, node.data.dir === "left" ? 180 : 0);
      }
    }

    function layoutAngle(parts, angle) {
      var layout = go.GraphObject.make(go.TreeLayout,
        {
          angle: angle,
          arrangement: go.TreeLayout.ArrangementFixedRoots,
          nodeSpacing: 5,
          layerSpacing: 20,
          setsPortSpot: false, // don't set port spots since we're managing them with our spotConverter function
          setsChildPortSpot: false
        });
      layout.doLayout(parts);
    }

    function layoutAll() {
      var root = myDiagram.findNodeForKey(0);
      if (root === null) return;
      myDiagram.startTransaction("Layout");
      // split the nodes and links into two collections
      var rightward = new go.Set(/*go.Part*/);
      var leftward = new go.Set(/*go.Part*/);
      root.findLinksConnected().each(function(link) {
        var child = link.toNode;
        if (child.data.dir === "left") {
          leftward.add(root);  // the root node is in both collections
          leftward.add(link);
          leftward.addAll(child.findTreeParts());
        } else {
          rightward.add(root);  // the root node is in both collections
          rightward.add(link);
          rightward.addAll(child.findTreeParts());
        }
      });
      // do one layout and then the other without moving the shared root node
      layoutAngle(rightward, 0);
      layoutAngle(leftward, 180);
      myDiagram.commitTransaction("Layout");
    }

    // Show the diagram's model in JSON format
    function save() {
      document.getElementById("mySavedModel").value = myDiagram.model.toJson();	  
      saveStaticDataToFile();	  
      myDiagram.isModified = false;
    }
    function load() {
      myDiagram.model = go.Model.fromJson(document.getElementById("mySavedModel").value);
    }
	
	function saveStaticDataToFile() 
	{
       var blob = new Blob([document.getElementById("mySavedModel").value],
       { type: "text/plain;charset=utf-8" });
          saveAs(blob, "mindMapRecord.txt");
    }
 
    window.addEventListener('DOMContentLoaded', init);
  </script>

<div id="sample">
  <div id="myDiagramDiv" style="border: solid 1px black; width:100%; height:300px;"></div>
  <p>
    A mind map editor, this GUI allows to create, delete, copy, move and laid out leaf node and/or subtrees
  </p>
  
   <p>
    Well, <b>let's try</b> to <a>move</a> leafs with your mouse. You can also <b>add, delete and modify </b> the map  by right clicking on a leaf to display the contextual menu. 
  </p>
  <p>
    When a node is deleted all of its children are deleted with it. When a node is dragged all its children are dragged with it.
  </p>
  
  
  <p>
    A mind map is a kind of spider diagram that organizes information around a central concept, with connecting branches.
  </p>
  
  <p>
    Otherwise, use the following form to :
  </p>

<h2>## Create a mind map</h2>
<p>Click the "Create map root" button to create a new root node.</p>
<p style="color:red"><b>Warning: the current mind map will be deleted.</b></p>
<form>
  <label for="rootname">root node name:</label>
  <input type="text" id="rootname" name="rootname" onKeyPress="return disableEnterKey(event)"><br><br>
</form>

<button onclick="createRoot()">Create map root</button>
<p id="demoroot"></p>

<h2>## Add a leaf (path) to the map</h2>

<p>Click the "Add Leaf" button to add a new leaf with the specified value and path. The path is separated with / as in this example: 
"path": you/work/hard,(do not include the root in the path as mentioned in the specifications)
"text": "Because reasons".
 Leave the path empty to add a leaf directely connected to the root</p>
<form>
  <label for="pathname">path:</label>
  <input type="text" id="pathname" name="pathname"><br><br>
  <label for="leafvalue">text:</label>
  <input type="text" id="leafvalue" name="leafvalue"><br><br>
</form>

<button onclick="addLeaf(); afterAddLeaf()">Add Leaf</button>
<p id="demoAdd"></p>
<p id="demoEntry"></p><br>
<p id="demoDebug"></p>
  
<h2>## Read a leaf (path) of the map</h2>

<p>Click the "Read Leaf" button to read a leaf in the specified path. The path is separated with / such as in this example: 
"path": you/play.</p>
 
<form>
  <label for="rpathname">path:</label>
  <input type="text" id="rpathname" name="rpathname" onKeyPress="return disableEnterKey(event)"><br><br>
</form>

<button onclick="readLeaf(); afterReadLeaf()">Read Lead</button>
<p id="demoRead"></p>
<p id="demoShow"></p><br>
<p id="demo"></p><br>
<!--
Expected response:
{
    "path": "i/like/potatoes",
    "text": "Because reasons"
}
-->
<h2>## Pretty print the whole tree of the mind map</h2>
<button onclick="layoutAll() ; clearLabels()">Pretty print</button>
<p>
 The whole tree of the mind map is printed in the map editor at the <b>top of the page</b>.
</p>
<br />

<script>
	function createRoot()
	{
		clearLabels();
		//document.getElementById('pathname').value = '';
		//document.getElementById('leafvalue').value = '';		
		var rootname_val = document.getElementById('rootname').value;
		rootname_val = getCleanPath(rootname_val);
		
		if(checkForSlash(rootname_val) == false)
		{document.getElementById('rootname').value = "";
			return;
		}
		
		if(checkKeyWord(rootname_val) == false)
		{
			document.getElementById('rootname').value = "";
			return;
		}	
	
		if(rootname_val != "")
		{
			var rootModel = "{\"class\": \"TreeModel\",\"nodeDataArray\": [{\"key\":0,\"text\":\"" +
			rootname_val + "\",\"loc\":\"0 0\"}]}";
			document.getElementById("mySavedModel").value = rootModel;
			load();
			save();		
			document.getElementById('rootname').value ="";
		}
	}

	function addLeaf() 
	{	
		clearLabels();
		
		var pathname_val = document.getElementById('pathname').value;
		pathname_val = getCleanPath(pathname_val);
	  	
		var idea = document.getElementById('leafvalue').value;
		idea = getCleanPath(idea);
	  
		 if(checkForSlash(idea) == false)
		{
			document.getElementById('leafvalue').value = "";
			return;
		}
	  
		if(checkKeyWord(idea) == false)
		{
			document.getElementById('leafvalue').value = "";
			return;
		}
		  
		if(idea == "")
		{
			var noideamsg = "Enter an idea to add leaf";
			document.getElementById("demoDebug").innerHTML = noideamsg.fontcolor("orange");
			return;
		}
	  
		if(isValidNode(idea))
		{
			var existmsg = "the node : " + idea + " already exist";
			document.getElementById("demoDebug").innerHTML = existmsg.fontcolor("orange");
			return;
		}	
		
		if(pathname_val == "")
		{
			if(idea != "")
			{
				const fewColors = ["skyblue", "darkseagreen", "palevioletred", "coral", "cadetblue", "chocolate", "aquamarine"];
				const randomcolor = Math.floor(Math.random() * fewColors.length);
			
			  addLeafAndLink(idea, fewColors[randomcolor], "right", 0);
			  layoutAll();
			  save();
			  document.getElementById("demoAdd").innerHTML = "\"path\":\" under the root" + "<br>";
			  document.getElementById("demoEntry").innerHTML = "\"text\":" + idea + "<br>";
			}
		  return;
		}

	var str2 = "\"text\":" + "<br>";
	var res = "\"path\":\"" + pathname_val + "<br>"; 
	document.getElementById("demoAdd").innerHTML = res.bold();	  
	  
	var validPath = isValidPath(pathname_val);

	var ressplit = pathname_val.split("/");	
	//Assume that all the nodes are different
	if(validPath == true)
	{
		var currentleafrow = getNodeRow(ressplit[ressplit.length - 1]);	

		if(pathname_val =="")
		{
			currentleafrow = getNodeRow("\"key\":0");
		}
		var currentleafkey = getNodeParam(currentleafrow, "key");
		
		if(currentleafkey != "")
		{		
			var currentleafdir = getNodeParam(currentleafrow, "dir");
			var currentleafbrush = getNodeParam(currentleafrow, "brush");
			
			currentleafdir = currentleafdir.substring(1, currentleafdir.length-1);
			currentleafbrush = currentleafbrush.substring(1, currentleafbrush.length-1);
		
			addLeafAndLink(idea, currentleafbrush, currentleafdir, currentleafkey);
			layoutAll();
			save();
			str2 = "\"text\":" + idea + "<br>";
		}		
	}
	else
	{
		if(ressplit[0] != "" && isValidRoot(ressplit[0]))
		{
			var rootmsg = "Do not include the root in the path";
			document.getElementById("demoDebug").innerHTML = rootmsg.fontcolor("orange");
		}
		else
		{
			var invalrootmsg = "invalid Path does not lead to root";
			document.getElementById("demoDebug").innerHTML = invalrootmsg.fontcolor("orange");	
		}		
	}	
	
	document.getElementById("demoEntry").innerHTML = str2.bold();	  
}

	function afterAddLeaf()
	{
		document.getElementById('pathname').value ="";
		document.getElementById('leafvalue').value ="";
	}
	
	function readLeaf() {

	clearLabels();
	//document.getElementById('pathname').value = '';
	//document.getElementById('leafvalue').value = '';
	  
	var rpathname_val = document.getElementById('rpathname').value;  
	    
	rpathname_val = getCleanPath(rpathname_val);
	
	var str2 = "\"text\":" + "<br>";	
    var str1 = "\"path\":\"" + rpathname_val + "<br>";
    document.getElementById("demoRead").innerHTML = str1.bold();

	var validPath = isValidPath(rpathname_val);
	var ressplit = rpathname_val.split("/");	
	//Assume that all the nodes are different
	if(validPath == true)
	{
		var currentleafrow = getNodeRow(ressplit[ressplit.length - 1]);	

		if(rpathname_val =="")
		{
			currentleafrow = getNodeRow("\"key\":0");
		}
		var currentleafkey = getNodeParam(currentleafrow, "key");
		
		if(currentleafkey != "")
		{
			var childText = getChildName(currentleafkey);
			if(childText != "")
			{
				str2 = "\"text\":" + childText + "<br>";
			}
		}	
	}
	else
	{
		if(ressplit[0] != "" && isValidRoot(ressplit[0]))
		{
			var rootmsg = "Do not include the root in the path";
			document.getElementById("demo").innerHTML = rootmsg.fontcolor("orange");
		}
		else
		{
			var invalrootmsg = "invalid Path does not lead to root";
			document.getElementById("demo").innerHTML = invalrootmsg.fontcolor("orange");	
		}
	}			
	
	document.getElementById("demoShow").innerHTML = str2.bold();
}

	function afterReadLeaf()
	{
		document.getElementById('rpathname').value ="";
	}
	
	function clearLabels()
	{
		document.getElementById("demoAdd").innerHTML = "";
		document.getElementById("demoEntry").innerHTML = "";
		document.getElementById("demoDebug").innerHTML = "";
		document.getElementById("demoRead").innerHTML = "";
		document.getElementById("demoShow").innerHTML = "";
		document.getElementById("demo").innerHTML = "";
	}

	function isValidRoot(txt)
	{
		var bres = false;
		var row = getNodeRow(txt);
		if(row != "")
		{
			var nodeKey = getNodeParam(row,"key");
			if(nodeKey == "0")
			{
				bres = true;
			}		
		}
		return bres;
	}

	function isValidNode(txt)
	{
		var bres = false;
		var row = getNodeRow(txt);
		if(row != "")
		{
			var nodeParent = getNodeParam(row, "parent");
			if(nodeParent != "")
			{
				bres = true;
			}		
		}
		return bres;
	}
	
	function isValidPath(pathValue)
	{
		var bres = false;
		
		if(pathValue =="")
		{
			return true;
		}
		
		var ressplit = pathValue.split("/");
		
		if(ressplit[0] != "" && isValidRoot(ressplit[0]))
		{
			document.getElementById("demo").innerHTML = "Do not include the root in the path";
			return false;
		}
		
		var numberOfColons = ressplit.length - 1; 		
		var parentLeafKey = 0;
		var cheminValid = true;
		while(cheminValid && numberOfColons >= 0)
		{
			if(isValidNode(ressplit[numberOfColons]))
			{
				var row = getNodeRow(ressplit[numberOfColons]);
				if(row != "")
				{
					parentLeafKey = getNodeParam(row, "parent");
					//document.getElementById("demo").innerHTML = parentLeafKey;	
					numberOfColons = numberOfColons - 1;
				}
			}
			else
			{
				parentLeafKey = -1;
				cheminValid = false;
			}
		}
		
		if(parentLeafKey == 0)
		{
			return true;
		}
		
		return bres;
	}

	function getCleanPath(path)
	{
		if(path.length > 0)
		{
			var lastChar = path.substr(path.length - 1);
			const arrayOfChars = ['/', '\\'];
			const foundl = arrayOfChars.find(v => (lastChar === v));
			if (foundl)
			{
				path = path.substr(0, path.length - 1);
			}
		}
		
		if(path.length > 0)
		{
			var firstChar = path.substr(0, 1);
			const arrayOfCharsl = ['/', '\\'];
			const foundf = arrayOfCharsl.find(w => (firstChar === w));
			if (foundf)
			{
				path = path.slice(1);
			}
		}
		
		return path;	
	}
	
	function checkKeyWord(idea)
	{
		var bres = true;
		if(idea === "key" ||
		idea === "dir" ||
		idea === "parent" ||
		idea === "brush" ||
		idea === "text")
	  {
		  bres = false;
		  {alert("key/dir/parent/brush/text are key words");		  
		}
	  }
	  
		if(idea.length > 0)
		{	
			var arrayOfChars = ['[', '{', '\"', ':', '}', ']', ','];
			
			for (var i = 0; i < arrayOfChars.length; i++)
			{
				if (idea.indexOf(arrayOfChars[i]) > -1) 
				{
					alert("[ { \" : } ] and , are prohibited characters");
					bres = false;
					return bres;
				}
			}
		}
	  
	  return bres;
	}
	
	function checkForSlash(pathIdea)
	{
		var bres = true;
		if(pathIdea.includes("/"))
		{alert("/ found inside your_string");
		bres = false;
		}
		else if (pathIdea.indexOf('\\') > -1)
		{
		alert("\ found inside your_string");
		bres = false;
		}
		return bres;
	}

	function getNodeRow(txt)
	{
		var bres = "";
		var mymodel = document.getElementById("mySavedModel").value;
		var ntxt = mymodel.search(txt);
		if(ntxt != -1)
		{
			var rowEndIndex = mymodel.indexOf('}', (ntxt + 1));
			if(rowEndIndex != -1)
			{
				var rowstr = mymodel.substring(0, rowEndIndex+1);
				var rowStartIndex = rowstr.lastIndexOf('{', );
				bres = rowstr.substr(rowStartIndex);
			}
		}	
		return bres;
	}
		
	function getNodeParam(txt, param)
	{
		var bres = "";
		var ntxt = txt.search(param);
		if(ntxt != -1)
		{
			var paramEndIndex = txt.indexOf(',', (ntxt + 1));
			if(paramEndIndex != -1)
			{
				bres = txt.substring(ntxt + param.length+2, paramEndIndex);
			}
		}	
		return bres;
	}

	function getChildName(parentkey)
	{
		var childname = "";	
		var tagParent = "\"parent\":" + parentkey +",";
		var rowChild = getNodeRow(tagParent);
		childname = getNodeParam(rowChild, "text"); 
		return childname;
	}
	
	//accepts an input variable (which key was key pressed) 
	function disableEnterKey(e)
	{  
		//create a variable to hold the number of the key that was pressed      
		var key; 
		//if the users browser is internet explorer 
		if(window.event)
		{      
			//store the key code (Key number) of the pressed key 
			key = window.event.keyCode;      
			//otherwise, it is firefox 
		} 
		else
		{      
			//store the key code (Key number) of the pressed key 
			key = e.which;      
		}     
		//if key 13 is pressed (the enter key) 
		if(key == 13)
		{      
			//do nothing 
			return false;      
			//otherwise 
		} 
		else
		{       
			//continue as normal (allow the key press for keys other than "enter") 
			return true; 
		}  
	} 
</script>
  
  <button id="SaveButton" onclick="save()">Save</button>
  <button onclick="load()">Load</button>
  <br />
  Diagram Model saved in JSON format:
  <br />
  <textarea id="mySavedModel" style="width:100%;height:400px">
{ "class": "TreeModel",
  "nodeDataArray": [
{"key":0,"text":"root","loc":"0 0"},
{"text":"amine","dir":"right","parent":0,"key":-12,"brush":"palevioletred","loc":"50 -13"},
{"text":"like","dir":"right","parent":-12,"key":-13,"brush":"palevioletred","loc":"105.4072265625 -26"},
{"text":"you","dir":"right","parent":0,"key":-14,"brush":"coral","loc":"73 48"},
{"text":"work","dir":"right","parent":-14,"key":-15,"brush":"coral","loc":"123 35"},
{"text":"play","dir":"right","parent":-14,"key":-16,"brush":"coral","loc":"123 61"},
{"text":"eat","dir":"right","parent":-12,"key":-17,"brush":"palevioletred","loc":"105.4072265625 0"},
{"text":"potatoes","dir":"right","parent":-13,"key":-18,"brush":"palevioletred","loc":"155.4072265625 -26"},
{"text":"tomatoes","dir":"right","parent":-17,"key":-19,"brush":"palevioletred","loc":"155.4072265625 0"},
{"text":"Because reasons","parent":-18,"key":-20,"brush":"palevioletred","loc":"225.28076171875 -26"},
{"text":"hard","dir":"right","parent":-15,"key":-21,"brush":"coral","loc":"173 35"},
{"text":"more","dir":"right","parent":-16,"key":-22,"brush":"coral","loc":"173 61"}
]
}
  </textarea>
  

<form action="actionpage.php", method="post">
  <label for="testparamname">Save and Exit</label>
  <input type="text" id="testparamname" name="testparamname"><br><br>
  <input type="submit" value="save and exit">
</form>
<p>Save the current mind map state in the sql db (db and table are created and db connection is established for demo)</p>
</div>
    </div>
    <!-- * * * * * * * * * * * * * -->
    <!--  End of Mind map challenge sample code  -->
  </div>
</body>

</html>
